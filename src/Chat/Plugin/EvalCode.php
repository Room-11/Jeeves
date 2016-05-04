<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Mutex;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\Response as ChatResponse;
use Room11\Jeeves\Chat\Message\Command;
use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\FormBody;
use Amp\Pause;

class EvalCode implements Plugin
{
    use CommandOnlyPlugin;

    // limit the number of requests while polling for results
    const REQUEST_LIMIT = 20;

    private $chatClient;

    private $httpClient;

    private $mutex;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;

        $this->mutex = new Mutex();
    }

    private function getResult(Command $command): \Generator {
        $code = $this->normalizeCode(implode(' ', $command->getParameters()));

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

        yield from $this->mutex->withLock(function() use($request) {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($request);

            /** @var ChatResponse $chatMessage */
            $chatMessage = yield from $this->chatClient->postMessage(
                $this->getMessage(
                    "Waiting for results",
                    "",
                    $response->getPreviousResponse()->getHeader("Location")[0])
            );

            yield from $this->pollUntilDone(
                $response->getPreviousResponse()->getHeader("Location")[0],
                $chatMessage->getMessageId()
            );
        });
    }

    private function normalizeCode($code) {
        $useInternalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($code, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_use_internal_errors($useInternalErrors);

        $code = $dom->textContent;

        if (strpos($code, '<?php') === false && strpos($code, '<?=') === false) {
            $code = "<?php {$code}";
        }

        return $code . ';';
    }

    private function pollUntilDone(string $snippetId, int $messageId): \Generator {
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

            yield from $this->chatClient->editMessage(
                $messageId,
                $this->getMessage(
                    $parsedResult["output"][0][0]["versions"],
                    htmlspecialchars_decode($parsedResult["output"][0][0]["output"]),
                    $snippetId
                )
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
            yield from $this->chatClient->postMessage(
                $this->getMessage(
                    $parsedResult["output"][0][$i]["versions"],
                    htmlspecialchars_decode($parsedResult["output"][0][$i]["output"]),
                    $snippetId
                )
            );
        }
    }

    private function getMessage(string $title, string $output, string $url): string {
        return sprintf(
            "[ [%s](%s) ] %s",
            $title,
            "https://3v4l.org" . $url,
            $this->formatResult($output)
        );
    }

    private function formatResult(string $result): string {
        $result = str_replace(["\r\n", "\r", "\n"], " ", $result);
        $result = str_replace("@", "@\u{200b}", $result);

        return $result;
    }

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator
    {
        if (!$command->getParameters()) {
            return;
        }

        yield from $this->getResult($command);
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getHandledCommands(): array
    {
        return ['eval', '&gt;'];
    }
}
