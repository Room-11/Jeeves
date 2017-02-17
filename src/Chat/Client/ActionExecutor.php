<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Pause;
use Amp\Promise;
use Ds\Queue;
use ExceptionalJSON\DecodeErrorException as JSONDecodeErrorException;
use Room11\Jeeves\Chat\Client\Actions\Action;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use function Amp\resolve;

class ActionExecutor
{
    private $httpClient;
    private $logger;

    /**
     * @var Queue[]
     */
    private $actionQueues = [];

    /**
     * @var bool[]
     */
    private $runningLoops = [];

    public function __construct(HttpClient $httpClient, Logger $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    private function executeAction(Action $action)
    {
        $exceptionClass = $action->getExceptionClassName();

        if (!$action->isValid()) {
            $action->fail(new $exceptionClass('Action no longer valid at point of execution'));
            return;
        }

        $attempt = 0;

        while ($attempt++ < $action->getMaxAttempts()) {
            /** @var HttpResponse $response */
            $this->logger->log(Level::DEBUG, 'Post attempt ' . $attempt);
            $response = yield $this->httpClient->request($action->getRequest());
            $this->logger->log(Level::DEBUG, 'Got response from server: ' . $response->getBody());

            if ($response->getStatus() === 409) {
                try {
                    $delay = $this->getBackOffDelay($response->getBody());
                } catch (\InvalidArgumentException $e) {
                    $errStr = 'Got a 409 response to an Action request that could not be decoded as a back-off delay';
                    $this->logger->log(Level::ERROR, $errStr, $response->getBody());
                    $action->fail(new $exceptionClass($errStr, $e->getCode(), $e));
                    return;
                }

                $this->logger->log(Level::DEBUG, "Backing off chat action execution for {$delay}ms");
                yield new Pause($delay);

                continue;
            }

            if ($response->getStatus() !== 200) {
                $errStr = 'Got a ' . $response->getStatus() . ' response to an Action request';
                $this->logger->log(Level::ERROR, $errStr, [$action->getRequest(), $response]);
                $action->fail(new $exceptionClass($errStr));
                return;
            }

            try {
                $decoded = json_try_decode($response->getBody(), true);
            } catch (JSONDecodeErrorException $e) {
                $errStr = 'A response that could not be decoded as JSON was received'
                    . ' (JSON decode error: ' . $e->getMessage() . ')';
                $this->logger->log(Level::ERROR, $errStr, $response->getBody());
                $action->fail(new $exceptionClass($errStr, $e->getCode(), $e));
                return;
            }

            $result = $action->processResponse($decoded, $attempt);

            if ($result < 1) {
                return;
            }

            if ($attempt >= $action->getMaxAttempts()) {
                break;
            }

            $this->logger->log(Level::DEBUG, "Backing off chat action execution for {$result}ms");
            yield new Pause($result);
        }

        $this->logger->log(Level::ERROR, 'Executing an action failed after ' . $action->getMaxAttempts() . ' attempts and I know when to quit');
    }

    private function getBackOffDelay(string $body): int
    {
        if (!preg_match('/You can perform this action again in (\d+) second/i', $body, $matches)) {
            throw new \InvalidArgumentException;
        }

        return (int)(($matches[1] + 1.1) * 1000);
    }

    private function executeActionsFromQueue(string $key): \Generator
    {
        $this->runningLoops[$key] = true;
        $this->logger->log(Level::DEBUG, "Starting action executor loop for {$key}");

        $queue = $this->actionQueues[$key];

        while ($queue->count() > 0) {
            try {
                yield from $this->executeAction($queue->pop());
            } catch (\Throwable $e) {
                $this->logger->log(
                    Level::DEBUG,
                    "Unhandled exception while executing ChatAction for {$key}: {$e->getMessage()}",
                    $e
                );
            }
        }

        $this->logger->log(Level::DEBUG, "Action executor loop terminating for {$key}");
        $this->runningLoops[$key] = false;
    }

    public function enqueue(Action $action): Promise
    {
        $key = $action->getRoom()->getIdentifier()->getIdentString();

        if (!isset($this->actionQueues[$key])) {
            $this->actionQueues[$key] = new Queue;
        }

        $this->actionQueues[$key]->push($action);

        if (empty($this->runningLoops[$key])) {
            resolve($this->executeActionsFromQueue($key));
        }

        return $action->promise();
    }
}
