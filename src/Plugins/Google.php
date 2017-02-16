<?php  declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\Chars;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\MessageResolver;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Room11\DOMUtils\domdocument_load_html;

class Google extends BasePlugin
{
    const USER_AGENT = 'Mozilla/5.0 (Windows NT 6.3; Trident/7.0; rv:11.0) like Gecko';
    const ENCODING = 'UTF-8';
    const ENCODING_REGEX = '#^utf-?8$#i';

    const BASE_URL = 'https://www.google.com/search';

    private $chatClient;

    private $httpClient;

    private $messageResolver;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, MessageResolver $messageResolver) {
        $this->chatClient  = $chatClient;
        $this->httpClient  = $httpClient;
        $this->messageResolver = $messageResolver;
    }

    private function getSearchURL(string $searchTerm): string
    {
        return self::BASE_URL . '?' . http_build_query([
            'q' => $searchTerm,
            'lr' => 'lang_en',
        ]);
    }

    private function postNoResultsMessage(Command $command): Promise
    {
        $message = sprintf(
            "Did you know? That `%s...` doesn't exist in the world! Cuz' GOOGLE can't find it :P",
            implode(' ', $command->getParameters())
        );

        return $this->chatClient->postReply($command, $message);
    }

    private function getResultNodes(\DOMXPath $xpath): \DOMNodeList
    {
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


            $descriptionNodes = $xpath->query('.//span[@class="st"]', $node);
            $description = $descriptionNodes->length !== 0
                ? $descriptionNodes->item(0)->textContent
                : 'No description available';

            $nodesInformation[] = [
                "url"         => $linkNode->getAttribute("href"),
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

        return trim(mb_substr($string, 0, $length - 1, self::ENCODING)) . Chars::ELLIPSIS;
    }

    private function formatDescription(string $description): string {
        static $removeLineBreaksExpr = '#(?:\r?\n)+#';
        static $stripDateExpr = '#^\s*[0-9]{1,2}\s+(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\s+[0-9]{4}\s*#iu';
        static $stripLeadingSeparatorExpr = '#^\s*(\.\.\.|-)\s*#u';

        $description = preg_replace($removeLineBreaksExpr, ' ', $description);
        $description = strip_tags($description);
        $description = preg_replace($stripDateExpr, '', $description);
        $description = preg_replace($stripLeadingSeparatorExpr, '', $description);
        $description = str_replace('...', Chars::ELLIPSIS, $description);

        return $description;
    }

    private function getPostMessage(array $searchResults, string $searchURL, string $searchTerm): string {
        $message = sprintf('Search for "%s" (%s)', $searchTerm, $searchURL);

        foreach ($searchResults as $result) {
            $message .= sprintf(
                "\n%s %s - %s (%s)",
                Chars::BULLET,
                $this->ellipsise($result['title'], 50),
                $this->ellipsise($result['description'], 100),
                $result['url']
            );
        }

        return $message;
    }

    public function search(Command $command)
    {
        if (!$command->hasParameters()) {
            return new Success();
        }

        $text = implode(' ', $command->getParameters());
        $searchTerm = yield $this->messageResolver->resolveMessageText($command->getRoom(), $text);
        $uri = $this->getSearchURL($searchTerm);

        $request = (new HttpRequest)
            ->setMethod('GET')
            ->setUri($uri)
            ->setHeader('User-Agent', self::USER_AGENT);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($request);

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command, "Google responded with {$response->getStatus()}");
        }

        if (preg_match('#charset\s*=\s*([^;]+)#i', trim(implode(', ', $response->getHeader('Content-Type'))), $match)
            && !preg_match('/' . preg_quote(self::ENCODING, '/') . '/i', $match[1])) {
            $body = iconv($match[1], self::ENCODING, $response->getBody());
        }

        if (empty($body)) {
            $body = $response->getBody();
        }

        $dom = domdocument_load_html($body);
        $xpath = new \DOMXPath($dom);
        $nodes = $this->getResultNodes($xpath);

        if($nodes->length === 0) {
            return $this->postNoResultsMessage($command);
        }

        $searchResults = $this->getSearchResults($nodes, $xpath);
        $postMessage   = $this->getPostMessage($searchResults, $uri, $searchTerm);

        return $this->chatClient->postMessage($command, $postMessage);
    }

    public function getDescription(): string
    {
        return 'Retrieves and displays search results from Google';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'google')];
    }
}
