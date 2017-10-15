<?php
/**
 * Created by PhpStorm.
 * User: saitama
 * Date: 15/10/17
 * Time: 2:47 PM
 */

namespace Room11\Jeeves\Plugins;


use Amp\Artax\HttpClient;
use Amp\Artax\Request;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\Jeeves\Utf8Chars;
use Room11\StackChat\Client\ChatClient;

class Manga extends BasePlugin {
    /**
     * @var ChatClient
     */
    private $chatClient;

    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $siteId;

    public function __construct($apiKey, $siteId, ChatClient $chatClient, HttpClient $httpClient) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
        $this->siteId = $siteId;
    }

    public function search(Command $command) {
        if (!$command->hasParameters()) {
            return $this->chatClient->postReply(
                $command,
                /** @lang text */ "Sorry I didn't catch that. Would you provide a term for the search?"
            );
        }

        $search = $command->getCommandText();

        $uri = $this->getApiUri();
        $params = $this->buildParams($search);

        $message = yield $this->chatClient->postMessage(
            $command,
            sprintf("_Searching for '%s'%s_", $search, Utf8Chars::ELLIPSIS)
        );

        $request = $this->buildRequest($uri, $params);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($request);

        if ($response->getStatus() !== 200) {
            return $this->chatClient->editMessage(
                $message,
                sprintf(
                    "Sorry, the [Manga Scraper API](https://market.mashape.com/doodle/manga-scraper) is currently unavailable. (%d)",
                    $response->getStatus()
                )
            );
        }

        $data = @json_decode($response->getBody(), true);

        if(count($data) === 0) {
            return $this->chatClient->editMessage(
                $message,
                sprintf("Ugh. There exists no manga named '%s' on %s.", $search, $this->siteId)
            );
        }

        return $this->chatClient->editMessage(
            $message,
            $this->buildFinalMessage($data, $search)
        );
    }

    private function formatDescription(string $description, int $length): string
    {
        $description = (strlen($description) > $length) ? substr($description, 0, 120) . Utf8Chars::ELLIPSIS : $description;
        static $removeLineBreaksExpr = '#(?:\r?\n)+#';
        static $stripLeadingSeparatorExpr = '#^\s*(\.\.\.|-)\s*#u';

        $description = preg_replace($removeLineBreaksExpr, ' ', $description);
        $description = preg_replace($stripLeadingSeparatorExpr, '', $description);
        $description = str_replace('...', Utf8Chars::ELLIPSIS, $description);

        return $description;
    }

    private function buildFinalMessage(array $data, string $query): string {
        $message = sprintf('Search result for %s', $query);
        foreach ($data as $datum) {
            $message .= $this->buildSingleResult($datum);
        }

        return $message;
    }

    private function buildSingleResult($datum) {
        return sprintf(
            "\n%s %s - %s (%s)",
            Utf8Chars::BULLET,
            $datum['name'],
            $this->formatDescription($datum['info'], 120),
            sprintf(
                'http://%s/manga/%s',
                $this->siteId,
                str_replace('-', '_', $datum['mangaId'])
            )
        );
    }

    private function buildRequest(string $uri, array $params) {
        return (new Request())
            ->setUri(sprintf('%s?%s', $uri, http_build_query($params)))
            ->setHeader('X-Mashape-Key', $this->apiKey)
            ->setHeader('Accept', 'text/plain');
    }

    private function buildParams(string $query): array {
        return [
            'cover' => 0,
            'l' => 3,
            'q' => $query,
            'info' => 1
        ];
    }

    private function getApiUri(): string {
        return sprintf("https://doodle-manga-scraper.p.mashape.com/%s/search", $this->siteId);
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string {
        return "Gets your mom.";
    }

    public function getCommandEndpoints(): array {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'manga')];
    }
}
