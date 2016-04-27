<?php  declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\Response;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;

class Google implements Plugin {
    const COMMAND = "google";

    private $chatClient;

    private $bitlyAccessToken;

    public function __construct(ChatClient $chatClient, string $bitlyAccessToken) {
        $this->chatClient       = $chatClient;
        $this->bitlyAccessToken = $bitlyAccessToken;
    }

    public function handle(Message $message): \Generator {
        if (!$this->validMessage($message)) {
            return;
        }

        yield from $this->getResult($message);
    }

    private function validMessage(Message $message): bool {
        return $message instanceof Command
            && $message->getCommand() === self::COMMAND
            && $message->getParameters();
    }

    private function getResult(Message $message): \Generator {
        $uri = "https://www.google.com/search?q=" . urlencode(implode(' ', $message->getParameters()));

        /** @var Response $response */
        $response = yield from $this->chatClient->request($uri);

        if ($response->getStatus() !== 200) {
            yield from $this->postErrorMessage();

            return;
        }

        $dom   = $this->buildDom($response->getBody());
        $xpath = new \DOMXPath($dom);
        $nodes = $this->getResultNodes($xpath);

        if($nodes->length === 0) {
            yield from $this->postNoResultsMessage($message);

            return;
        }

        $searchResults = $this->getSearchResults($nodes, $xpath);
        $postMessage   = yield from $this->getPostMessage($searchResults, $message);

        yield from $this->chatClient->postMessage($postMessage);
    }

    private function postErrorMessage(): \Generator {
        yield from $this->chatClient->postMessage(
            "It was Google's fault, not mine."
        );
    }

    private function postNoResultsMessage(Message $message): \Generator {
        yield from $this->chatClient->postMessage(sprintf(
            ":%s Did you know? That `%s...` doesn't exist in the world! Cuz' GOOGLE can't find it :P",
            $message->getOrigin(),
            substr(implode(" ", $message->getParameters()), 0, 60)
        ));
    }

    private function buildDom($body): \DOMDocument {
        $internalErrors = libxml_use_internal_errors(true);
        $dom            = new \DOMDocument();
        $body           = utf8_encode($body);

        $dom->loadHTML($body);

        libxml_use_internal_errors($internalErrors);

        return $dom;
    }

    private function getResultNodes(\DOMXPath $xpath): \DOMNodeList {
        return $xpath->evaluate("//*[contains(concat(' ', normalize-space(@class), ' '), ' g ')]");
    }

    private function getSearchResults(\DOMNodeList $nodes, \DOMXPath $xpath): array {
        $nodesInformation = [];

        foreach ($nodes as $node) {
            $linkNodes = $xpath->evaluate(".//h3/a", $node);

            if (!$linkNodes->length) {
                continue;
            }

            $linkNode = $linkNodes->item(0);

            if(preg_match('~^/url\?q=([^&]*)~', $linkNode->getAttribute("href"), $matches) == false) {
                continue;
            }

            $nodesInformation[] = [
                "url"         => $matches[1],
                "title"       => $linkNode->textContent,
                "description" => $this->formatDescription($xpath->query('.//span[@class="st"]', $node)->item(0)->textContent),
            ];

            if (count($nodesInformation) === 3) {
                break;
            }
        }

        return $nodesInformation;
    }

    private function formatDescription(string $description): string {
        $cleanedDescription = str_replace(["\r\n", "\r", "\n"], " ", $description);
        $cleanedDescription = strip_tags($cleanedDescription);

        $ellipsis = mb_strlen($cleanedDescription, "UTF-8") > 55 ? "…" : "";

        return mb_substr($cleanedDescription, 0, 55, "UTF-8") . $ellipsis;
    }

    private function getPostMessage(array $searchResults, Message $message): \Generator {
        $postMessage = "";

        $urls = yield from $this->getShortenedUrls($searchResults, $message);

        foreach ($searchResults as $index => $result) {
            $newMessage = sprintf(
                " **[%s](%s)** %s  |",
                $result["title"],
                $urls[$index],
                $result["description"]
            );

            if (mb_strlen($postMessage . $newMessage, "UTF-8") > 500) {
                return $postMessage;
            }

            $postMessage .= $newMessage;
        }

        $googleLinkMessage = " **[Search Url]($urls[3])**";

        if (mb_strlen($postMessage . $googleLinkMessage, "UTF-8") <= 500) {
            $postMessage .= $googleLinkMessage;
        }

        return $postMessage;
    }

    private function getShortenedUrls(array $searchResults, Message $message): \Generator {
        $urls = array_map(function($result) {
            return sprintf(
                "https://api-ssl.bitly.com/v3/shorten?access_token=%s&longUrl=%s",
                $this->bitlyAccessToken,
                $result["url"]
            );
        }, $searchResults);

        $urls[] = sprintf(
            "https://api-ssl.bitly.com/v3/shorten?access_token=%s&longUrl=%s",
            $this->bitlyAccessToken,
            "https://www.google.com/search?q=" . urlencode(implode(' ', $message->getParameters()))
        );

        $responses = yield from $this->chatClient->requestMulti($urls);

        return array_map(function($response) {
            return json_decode($response->getBody(), true)["data"]["url"];
        }, $responses);
    }
}
