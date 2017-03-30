<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Artax\Uri;
use Amp\Deferred;
use Amp\Pause;
use Amp\Promise;
use Amp\Promisor;
use Amp\Success;
use Ds\Queue;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Client\PostFlags;
use Room11\StackChat\Entities\PostedMessage;
use function Amp\resolve;

class EvalCode extends BasePlugin
{
    // limit the number of requests while polling for results
    private const POLL_REQUEST_LIMIT = 20;
    private const POLL_INTERVAL_MS = 3500;
    private const BASE_URL = 'https://3v4l.org';
    private const POST_FLAGS = PostFlags::SINGLE_LINE;

    private $chatClient;
    private $httpClient;

    private $queue;
    private $haveLoop = false;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;

        $this->queue = new Queue;
    }

    private function normalizeCode($code)
    {
        if (strpos($code, '<?php') === false && strpos($code, '<?=') === false) {
            $code = "<?php {$code}";
        }

        return $code . ';';
    }

    private function pollUntilDone(string $url, PostedMessage $firstMessage, string $firstMessageText)
    {
        $request = (new HttpRequest)
            ->setUri($url)
            ->setHeader("Accept", "application/json")
        ;

        $postedMessages = [[$firstMessage, $firstMessageText]];
        $room = $firstMessage->getRoom();
        $pauseDuration = self::POLL_INTERVAL_MS;
        $requests = 0;

        do {
            if ($pauseDuration > 0) {
                yield new Pause($pauseDuration);
            }

            /** @var HttpResponse $result */
            $result = yield $this->httpClient->request($request);
            $parsedResult = json_try_decode($result->getBody(), true);

            $messages = [];

            for ($i = 1; isset($parsedResult['output'][$i]) && $i < 4; $i++) {
                $messages[$i] = $this->generateMessageFromOutput($parsedResult['output'][$i], $url);
            }

            $actionStart = microtime(true);

            foreach ($messages as $i => $text) {
                if (!isset($postedMessages[$i])) {
                    $postedMessages[$i] = [yield $this->chatClient->postMessage($room, $text, self::POST_FLAGS), $text];
                } else {
                    yield $this->chatClient->editMessage($postedMessages[$i], $text, PostFlags::SINGLE_LINE);
                }
            }

            $pauseDuration = self::POLL_INTERVAL_MS - (int)floor((microtime(true) - $actionStart) * 1000);
        } while (++$requests < self::POLL_REQUEST_LIMIT && $parsedResult['script']['state'] === 'busy');
    }

    private function getMessageText(string $title, string $output, string $url): string
    {
        return trim(sprintf('[ [%s](%s) ] %s', $title, $url, $output));
    }

    private function generateMessageFromOutput(array $output, string $url): string
    {
        return $this->getMessageText($output["versions"], htmlspecialchars_decode($output["output"]), $url);
    }

    private function doEval(HttpRequest $request, Command $command): \Generator
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($request);

        $requestUri = $request->getUri();

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postReply($command, "Got HTTP response code {$response->getStatus()} from `{$requestUri}` :-(");
        }

        $previousResponse = $response->getPreviousResponse();

        if ($previousResponse === null) {
            return $this->chatClient->postReply($command, "I wasn't redirected by `{$requestUri}` like I expected :-(");
        }

        try {
            $location = $previousResponse->getHeader("Location");
        } catch (\DomainException $e) {
            $location = [];
        }

        if (!isset($location[0])) {
            return $this->chatClient->postReply($command, "I didn't get a redirect location from `{$requestUri}` :-(");
        }

        $targetUri = (string)(new Uri($requestUri))->resolve($location[0]);

        $text = $this->getMessageText('Waiting for results', '', $targetUri);

        /** @var PostedMessage $chatMessage */
        $chatMessage = yield $this->chatClient->postMessage($command, $text, PostFlags::SINGLE_LINE);

        yield from $this->pollUntilDone($targetUri, $chatMessage, $text);
    }

    private function executeActionsFromQueue()
    {
        $this->haveLoop = true;

        while ($this->queue->count() > 0) {
            /** @var HttpRequest $request */
            /** @var Command $command */
            /** @var Promisor $promisor */
            list($request, $command, $promisor) = $this->queue->pop();

            try {
                $promisor->succeed(yield from $this->doEval($request, $command));
            } catch (\Throwable $e) {
                $promisor->fail($e);
            }
        }

        $this->haveLoop = false;
    }

    public function eval(Command $command): Promise
    {
        if (!$command->hasParameters()) {
            return new Success();
        }

        $code = $this->normalizeCode($command->getCommandText());

        $body = (new FormBody)
            ->addField("title", "")
            ->addField("code", $code)
        ;

        $request = (new HttpRequest)
            ->setUri(self::BASE_URL . "/new")
            ->setMethod("POST")
            ->setHeader("Accept", "application/json")
            ->setBody($body)
        ;

        $deferred = new Deferred;

        $this->queue->push([$request, $command, $deferred]);
        if (!$this->haveLoop) {
            resolve($this->executeActionsFromQueue())->when(function(?\Throwable $error) use($deferred) {
                if ($error) {
                    $deferred->fail($error);
                }
            });
        }

        return $deferred->promise();
    }

    public function getName(): string
    {
        return '3v4l';
    }

    public function getDescription(): string
    {
        return 'Executes code snippets on 3v4l.org and displays the output';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Eval', [$this, 'eval'], 'eval')];
    }
}
