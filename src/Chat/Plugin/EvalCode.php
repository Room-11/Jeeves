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
    const COMMANDS = ['eval', '>'];

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

        if (strpos($code, "<?php") === false) {
            $code = "<?php " . $code;
        }

        $body = (new FormBody)
            ->addField("title", "")
            ->addField("code", $code)
        ;

        $request = (new Request)
            ->setUri("https://3v4l.org/new")
            ->setMethod("POST")
            ->setBody($body)
        ;

        $response = yield from $this->chatClient->request($request);

        // 3v4l uses only paths for redirects
        $result = yield from $this->pollUntilDone(
            "https://3v4l.org" . $response->getPreviousResponse()->getHeader("Location")[0]
        );

        yield from $this->chatClient->postMessage(
            $this->getMessage($result, $response->getPreviousResponse()->getHeader("Location")[0])
        );
    }

    private function normalizeCode($code) {
        $code = html_entity_decode($code, ENT_QUOTES);

        $useInternalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($code, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_use_internal_errors($useInternalErrors);

        $code = $dom->textContent;

        return $code;
    }

    /**
     * 3v4l responds with full HTML pages while polling #idonteven
     *
     * It seems like there is a class `busy` when the result is not complete yet and a class `done` when it is finished
     * somewhere in the html. inb4 this method breaks when you look at it the wrong way or Jon changes something
     */
    private function pollUntilDone(string $snippetUrl): \Generator {
        $requests = 0;

        while (true && $requests <= self::REQUEST_LIMIT) {
            $requests++;

            $result = yield from $this->chatClient->request($snippetUrl);

            $useInternalErrors = libxml_use_internal_errors(true);

            $dom = new \DOMDocument();
            $dom->loadHTML($result->getBody());

            libxml_use_internal_errors($useInternalErrors);

            $xpath = new \DOMXPath($dom);

            $nodes = $xpath->query("//*[contains(concat(\" \", normalize-space(@class), \" \"), \" busy \")]");

            if (!$nodes->length) {
                return $this->parseResponse($result->getBody());
            }

            yield new Pause(2500);
        }
    }

    private function parseResponse(string $body): array {
        $useInternalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($body);

        libxml_use_internal_errors($useInternalErrors);

        $resultPane = $dom->getElementById("tab")->getElementsByTagName("dl")->item(0);

        return [
            "title"  => $resultPane->getElementsByTagName("dt")->item(0)->textContent,
            "result" => $resultPane->getElementsByTagName("dd")->item(0)->textContent,
        ];
    }

    private function getMessage(array $result, string $url): string {
        return sprintf(
            "[ [%s](%s) ] %s",
            $result["title"],
            "https://3v4l.org" . $url,
            $result["result"]
        );
    }
}
