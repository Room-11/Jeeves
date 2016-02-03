<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;
use Amp\Artax\FormBody;
use Amp\Artax\Request;
use Amp\Pause;

class EvalCode implements Plugin
{
    const COMMANDS = ['eval', '&gt;'];

    // limit the number of requests while polling for results
    const REQUEST_LIMIT = 20;

    private $chatClient;

    public function __construct(ChatClient $chatClient) {
        $this->chatClient = $chatClient;
    }

    public function handle(Message $message): \Generator {
        if (!$this->validMessage($message)) {
            return;
        }

        yield from $this->getResult($message);
    }

    private function validMessage(Message $message): bool {
        return $message instanceof Command
            && in_array($message->getCommand(), self::COMMANDS, true)
            && $message->getParameters();
    }

    private function getResult(Message $message): \Generator {
        $code = $this->normalizeCode(implode(' ', $message->getParameters()));

        $body = (new FormBody)
            ->addField("title", "")
            ->addField("code", $code)
        ;

        $request = (new Request)
            ->setUri("https://3v4l.org/new")
            ->setMethod("POST")
            ->setHeader("Accept", "application/json")
            ->setBody($body)
        ;

        $response = yield from $this->chatClient->request($request);

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
    }

    private function normalizeCode($code) {
        $code = html_entity_decode($code, ENT_QUOTES);

        $useInternalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($code, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_use_internal_errors($useInternalErrors);

        $code = $dom->textContent;

        if (strpos($code, "<?php") === false) {
            $code = "<?php " . $code;
        }

        return $code;
    }

    private function pollUntilDone(string $snippetId, int $messageId): \Generator {
        $requests = 0;

        yield new Pause(3500);

        $request = (new Request)
            ->setUri("https://3v4l.org" . $snippetId)
            ->setHeader("Accept", "application/json")
        ;

        while (true && $requests <= self::REQUEST_LIMIT) {
            $requests++;

            $result = yield from $this->chatClient->request($request);

            $parsedResult = json_decode($result->getBody(), true);

            yield from $this->chatClient->editMessage(
                $messageId,
                $this->getMessage(
                    $parsedResult["output"][0][0]["versions"],
                    $parsedResult["output"][0][0]["output"],
                    $snippetId
                )
            );

            if ($parsedResult["script"]["state"] !== "busy") {
                yield new Pause(2500);

                for ($i = 1; $i < count($parsedResult["output"][0]) && $i < 4; $i++) {
                    yield new Pause(3500);

                    yield from $this->chatClient->postMessage(
                        $this->getMessage(
                            $parsedResult["output"][0][$i]["versions"],
                            $parsedResult["output"][0][$i]["output"],
                            $snippetId
                        )
                    );
                }

                return;
            }

            yield new Pause(3500);
        }
    }


    private function getMessage(string $title, string $output, string $url): string {
        return sprintf(
            "[ [%s](%s) ] %s",
            $title,
            "https://3v4l.org" . $url,
            str_replace(["\r", "\n"], " ", $output)
        );
    }
}
