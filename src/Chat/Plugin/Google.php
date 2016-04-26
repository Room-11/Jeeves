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
        $this->chatClient = $chatClient;
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
            yield from $this->chatClient->postMessage(
                "It was Google's fault, not mine."
            );
            return;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $google = utf8_encode($response->getBody());
        $dom->loadHTML($google);
        libxml_use_internal_errors($internalErrors);
        $xpath = new \DOMXPath($dom);

        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' g ')]");

        if($nodes->length === 0) {
            yield from $this->chatClient->postMessage(sprintf(
                ":%s Did you know? That `%s...` doesn't exist in the world! Cuz' GOOGLE can't find it :P",
                $message->getOrigin(),
                substr(implode(" ", $message->getParameters()), 0, 60)
            ));
            return;
        }

        $length = min(3, $nodes->length);
        $toPostMessage = "";

        for($i = 0; $i < $length; $i++) {

            $currentNode = $nodes[$i];
            $nodeLink= $xpath->query("//h3/a", $currentNode);
            $nodeLinkText = $nodeLink->item($i)->textContent;
            $nodeLink = $nodeLink->item($i)->getAttribute("href");
            $nodeDescription = substr(strip_tags(nl2br($xpath->query('//span[@class="st"]', $currentNode)->item($i)->textContent)), 0, 55);
            if(preg_match('~^/url\?q=([^&]*)~', $nodeLink, $matches) == false) {
                continue;
            }
            $link = $matches[1];

            $apiUri = sprintf(
                "https://api-ssl.bitly.com/v3/shorten?access_token=%s&longUrl=%s",
                $this->bitlyAccessToken,
                $link
            );

            $shortener = yield from $this->chatClient->request($apiUri);
            $shortened = json_decode($shortener->getBody(), true);
            $shortenedLink = $shortened["data"]["url"];
            $toAppendMessage = sprintf(
                "  **[%s](%s)** %s...  |",
                $nodeLinkText,
                $shortenedLink,
                $nodeDescription
            );
            if(strlen($toPostMessage) + strlen($toAppendMessage) > 500) {
                continue;
            }
            $toPostMessage .= $toAppendMessage;
        }

        $apiUri = sprintf(
            "https://api-ssl.bitly.com/v3/shorten?access_token=%s&longUrl=%s",
            $this->bitlyAccessToken,
            $uri
        );
        $googleSearchBitlyLink = yield from $this->chatClient->request($apiUri);
        $googleSearchBitlyLink = json_decode($googleSearchBitlyLink->getBody(), true)["data"]["url"];
        if(strlen($toPostMessage) + strlen("  **[Search Url]($googleSearchBitlyLink)**") > 500) {
            $toPostMessage = substr($toPostMessage, 0, 500 - (strlen("  **[Search Url]($googleSearchBitlyLink)**") + 1));
            $toPostMessage .= "|";
        }
        $toPostMessage .= "  **[Search Url]($googleSearchBitlyLink)**";
        $toPostMessage = str_replace("\r", " ", $toPostMessage);
        $toPostMessage = str_replace("\r\n", " ", $toPostMessage);
        yield from $this->chatClient->postMessage(str_replace("\n", " ", $toPostMessage));
    }
}
