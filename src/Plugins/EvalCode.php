<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Mutex\QueuedExclusiveMutex;
use Amp\Pause;
use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\Entities\PostedMessage;
use Room11\Jeeves\Chat\Client\PostFlags;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Room11\DOMUtils\domdocument_load_html;

class EvalCode extends BasePlugin
{
    // limit the number of requests while polling for results
    const REQUEST_LIMIT = 20;

    private $chatClient;

    private $httpClient;

    private $mutex;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;

        $this->mutex = new QueuedExclusiveMutex();
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
                    $parsedResult["output"][0][0]["versions"],
                    htmlspecialchars_decode($parsedResult["output"][0][0]["output"]),
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

        for ($i = 1; isset($parsedResult["output"][0][$i]) && $i < 4; $i++) {
            yield $this->chatClient->postMessage(
                $message->getRoom(),
                $this->getMessageText(
                    $parsedResult["output"][0][$i]["versions"],
                    htmlspecialchars_decode($parsedResult["output"][0][$i]["output"]),
                    $snippetId
                ),
                PostFlags::SINGLE_LINE
            );
        }
    }

    private function getMessageText(string $title, string $output, string $url): string {
        return sprintf('[ [%s](%s) ] %s', $title, 'https://3v4l.org' . $url, $output);
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

        return $this->mutex->withLock(function() use($request, $command) {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($request);

            /** @var PostedMessage $chatMessage */
            $chatMessage = yield $this->chatClient->postMessage(
                $command->getRoom(),
                $this->getMessageText(
                    "Waiting for results",
                    "",
                    $response->getPreviousResponse()->getHeader("Location")[0]),
                PostFlags::SINGLE_LINE
            );

            yield from $this->pollUntilDone(
                $response->getPreviousResponse()->getHeader("Location")[0],
                $chatMessage
            );
        });
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
