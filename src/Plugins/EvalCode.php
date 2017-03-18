<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Deferred;
use Amp\Pause;
use Amp\Promise;
use Amp\Promisor;
use Amp\Success;
use Ds\Queue;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client;
use Room11\StackChat\Client\PostFlags;
use Room11\StackChat\Entities\PostedMessage;
use function Amp\resolve;

class EvalCode extends BasePlugin
{
    // limit the number of requests while polling for results
    private const REQUEST_LIMIT = 20;

    private $chatClient;

    private $httpClient;

    private $queue;
    private $haveLoop = false;

    public function __construct(Client $chatClient, HttpClient $httpClient) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;

        $this->queue = new Queue;
    }

    private function normalizeCode($code) {
        if (strpos($code, '<?php') === false && strpos($code, '<?=') === false) {
            $code = "<?php {$code}";
        }

        return $code . ';';
    }

    private function pollUntilDone(string $snippetId, PostedMessage $message): \Generator {
        $requests = 0;
        $parsedResult = [];

        yield new Pause(3500);

        $request = (new HttpRequest)
            ->setUri("https://3v4l.org" . $snippetId)
            ->setHeader("Accept", "application/json")
        ;

        while ($requests++ <= self::REQUEST_LIMIT) {
            /** @var HttpResponse $result */
            $result = yield $this->httpClient->request($request);
            $parsedResult = json_decode($result->getBody(), true);

            $editStart = microtime(true);

            yield $this->chatClient->editMessage(
                $message,
                $this->getMessageText(
                    $parsedResult["output"][0]["versions"],
                    htmlspecialchars_decode($parsedResult["output"][0]["output"]),
                    $snippetId
                ),
                PostFlags::SINGLE_LINE
            );

            if ($parsedResult["script"]["state"] !== "busy") {
                break;
            }

            $editDuration = (int)floor((microtime(true) - $editStart) * 1000);
            if ($editDuration < 3500) {
                yield new Pause(3500 - $editDuration);
            }
        }

        for ($i = 1; isset($parsedResult["output"][$i]) && $i < 4; $i++) {
            yield $this->chatClient->postMessage(
                $message,
                $this->getMessageText(
                    $parsedResult["output"][$i]["versions"],
                    htmlspecialchars_decode($parsedResult["output"][$i]["output"]),
                    $snippetId
                ),
                PostFlags::SINGLE_LINE
            );
        }
    }

    private function getMessageText(string $title, string $output, string $url): string {
        return sprintf('[ [%s](%s) ] %s', $title, 'https://3v4l.org' . $url, $output);
    }

    private function doEval(HttpRequest $request, Command $command): \Generator
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($request);

        $location = $response->getPreviousResponse()->getHeader("Location")[0];
        $text = $this->getMessageText('Waiting for results', '', $location);

        /** @var PostedMessage $chatMessage */
        $chatMessage = yield $this->chatClient->postMessage($command, $text, PostFlags::SINGLE_LINE);

        yield from $this->pollUntilDone($location, $chatMessage);
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
                yield from $this->doEval($request, $command);
                $promisor->succeed();
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

        $code = $this->normalizeCode($command->getText());

        $body = (new FormBody)
            ->addField("title", "")
            ->addField("code", $code)
        ;

        $request = (new HttpRequest)
            ->setUri("https://3v4l.org/new")
            ->setMethod("POST")
            ->setHeader("Accept", "application/json")
            ->setBody($body)
        ;

        $deferred = new Deferred;

        $this->queue->push([$request, $command, $deferred]);
        if (!$this->haveLoop) {
            resolve($this->executeActionsFromQueue());
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
