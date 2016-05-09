<?php  declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use function Amp\all;
use function Room11\DOMUtils\domdocument_load_html;

class Google implements Plugin {
    use CommandOnlyPlugin;

    const ENCODING = "UTF-8";
    const ELLIPSIS = "\xE2\x80\xA6";
    const BULLET   = "\xE2\x80\xA2";

    const BASE_URL = 'https://www.google.com/search';

    private $chatClient;

    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient) {
        $this->chatClient  = $chatClient;
        $this->httpClient  = $httpClient;
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
            yield from $this->chatClient->postMessage(
                $command->getRoom(),
                "It was Google's fault, not mine."
            );
            return;
        }

        $dom = domdocument_load_html($response->getBody());
        $xpath = new \DOMXPath($dom);
        $nodes = $this->getResultNodes($xpath);

        if($nodes->length === 0) {
            yield from $this->postNoResultsMessage($command);

            return;
        }

        $searchResults = $this->getSearchResults($nodes, $xpath);
        $postMessage   = $this->getPostMessage($searchResults, $uri, $command);

        yield from $this->chatClient->postMessage($command->getRoom(),$postMessage);
    }

    private function postNoResultsMessage(Command $command): \Generator {
        yield from $this->chatClient->postReply(
            $command, sprintf("Did you know? That `%s...` doesn't exist in the world! Cuz' GOOGLE can't find it :P", implode(' ', $command->getParameters()))
        );
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

    private function getPostMessage(array $searchResults, string $searchURL, Command $command): string {
        $searchTerm = implode(' ', $command->getParameters());

        $message = sprintf('Search for "%s" (%s)', $searchTerm, $searchURL);

        foreach ($searchResults as $result) {
            $message .= sprintf(
                "\n%s %s - %s (%s)",
                self::BULLET,
                $this->ellipsise($result['title'], 50),
                $this->ellipsise($result['description'], 100),
                $result['url']
            );
        }

        return $message;
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
