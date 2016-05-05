<?php  declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Bitly\Client as BitlyClient;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use function Amp\all;

class Google implements Plugin {
    use CommandOnlyPlugin;

    const ENCODING = "UTF-8";
    const ELLIPSIS = "\xE2\x80\xA6";
    const BULLET   = "\xE2\x80\xA2";

    const BASE_URL = 'https://www.google.com/search';

    private $chatClient;

    private $httpClient;

    private $bitlyClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, BitlyClient $bitlyClient) {
        $this->chatClient  = $chatClient;
        $this->httpClient  = $httpClient;
        $this->bitlyClient = $bitlyClient;
    }

    private function getSearchURL(Command $command): string
    {
        return self::BASE_URL . '?' . http_build_query([
            'q' => implode(' ', $command->getParameters()),
            'lr' => 'lang_en',
        ]);
    }

    private function getResult(Command $command): \Generator {
        $uri = $this->getSearchURL($command);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($uri);

        if ($response->getStatus() !== 200) {
            yield from $this->postErrorMessage();

            return;
        }

        $dom   = $this->buildDom($response->getBody());
        $xpath = new \DOMXPath($dom);
        $nodes = $this->getResultNodes($xpath);

        if($nodes->length === 0) {
            yield from $this->postNoResultsMessage($command);

            return;
        }

        $searchResults = $this->getSearchResults($nodes, $xpath);
        $postMessage   = yield from $this->getPostMessage($searchResults, $uri, $command);

        yield from $this->chatClient->postMessage($postMessage);
    }

    private function postErrorMessage(): \Generator {
        yield from $this->chatClient->postMessage(
            "It was Google's fault, not mine."
        );
    }

    private function postNoResultsMessage(Command $command): \Generator {
        yield from $this->chatClient->postReply(
            $command, sprintf("Did you know? That `%s...` doesn't exist in the world! Cuz' GOOGLE can't find it :P", implode(' ', $command->getParameters()))
        );
    }

    private function buildDom($body): \DOMDocument {
        $internalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $body);

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

            /** @var \DOMElement $linkNode */
            $linkNode = $linkNodes->item(0);

            if (!preg_match('~^/url\?q=([^&]*)~', $linkNode->getAttribute("href"), $matches)) {
                continue;
            }

            $descriptionNodes = $xpath->query('.//span[@class="st"]', $node);
            $description = $descriptionNodes->length !== 0
                ? $descriptionNodes->item(0)->textContent
                : 'No description available';

            $nodesInformation[] = [
                "url"         => urldecode($matches[1]), // we got it from a query string param
                "title"       => $linkNode->textContent,
                "description" => $this->formatDescription($description),
            ];

            if (count($nodesInformation) === 3) {
                break;
            }
        }

        return $nodesInformation;
    }

    private function ellipsise(string $string, int $length): string
    {
        if (mb_strlen($string, self::ENCODING) < $length) {
            return $string;
        }

        return trim(mb_substr($string, 0, $length - 1, self::ENCODING)) . self::ELLIPSIS;
    }

    private function formatDescription(string $description): string {
        static $removeLineBreaksExpr = '#(?:\r?\n)+#';
        static $stripDateExpr = '#^\s*[0-9]{1,2}\s+(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\s+[0-9]{4}\s*#iu';
        static $stripLeadingSeparatorExpr = '#^\s*(\.\.\.|-)\s*#u';

        $description = preg_replace($removeLineBreaksExpr, ' ', $description);
        $description = strip_tags($description);
        $description = preg_replace($stripDateExpr, '', $description);
        $description = preg_replace($stripLeadingSeparatorExpr, '', $description);
        $description = str_replace('...', self::ELLIPSIS, $description);

        return $description;
    }

    private function getPostMessage(array $searchResults, string $searchURL, Command $command): \Generator {
        $urls = yield from $this->getShortenedUrls($searchResults, $searchURL);

        $searchTerm = implode(' ', $command->getParameters());
        var_dump($searchTerm);

        $length = 52; // this is how many chars there are in the template strings (incl bullets)
        $length += mb_strlen($searchTerm, self::ENCODING) + strlen($urls[$searchURL]);
        foreach ($searchResults as $result) {
            $length += max(mb_strlen($result['title'], self::ENCODING), 30) + strlen($urls[$result['url']]);
        }

        $descriptionLength = (int)floor((499 - $length) / 3); // 499 chars is the max before "see full text"

        $message = sprintf('Search for "%s" (%s)', $searchTerm, $urls[$searchURL]);

        foreach ($searchResults as $result) {
            $message .= sprintf(
                "\n%s %s - %s (%s)",
                self::BULLET,
                $this->ellipsise($result['title'], 30),
                $this->ellipsise($result['description'], $descriptionLength),
                $urls[$result['url']]
            );
        }

        return $message;
    }

    private function getShortenedUrls(array $searchResults, string $searchURL): \Generator {
        $urls = array_merge(array_map(function($result) { return $result["url"]; }, $searchResults), [$searchURL]);

        return yield from $this->bitlyClient->shortenMulti($urls);
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
        return ["google"];
    }
}
