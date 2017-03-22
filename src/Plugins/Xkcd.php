<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\GoogleSearcher\Searcher as GoogleSearcher;
use Room11\GoogleSearcher\SearchResultSet;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;

class Xkcd extends BasePlugin
{
    private const NOT_FOUND_COMIC = 'https://xkcd.com/1334/';

    private $chatClient;
    private $httpClient;
    private $searcher;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, GoogleSearcher $searcher)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->searcher = $searcher;
    }

    public function search(Command $command)
    {
        if (!$command->hasParameters()) {
            return null;
        }

        $searchTerm = 'site:xkcd.com intitle:"xkcd: " ' . implode(' ', $command->getParameters());

        /** @var SearchResultSet $results */
        $results = yield $this->searcher->search($searchTerm);

        foreach ($results->getResults() as $result) {
            if (preg_match('~^https?://xkcd\.com/\d+/?~', $result->getUrl(), $matches)) {
                return $this->chatClient->postMessage($command, $matches[0]);
            }
        }

        $comicId = $command->getParameter(0);

        if (!\ctype_digit($comicId)) {
            return $this->chatClient->postMessage($command, self::NOT_FOUND_COMIC);
        }

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request('https://xkcd.com/' . $comicId);

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command, self::NOT_FOUND_COMIC);
        }

        return $this->chatClient->postMessage($command, 'https://xkcd.com/' . $comicId);
    }

    public function getName(): string
    {
        return 'xkcd';
    }

    public function getDescription(): string
    {
        return 'Searches for relevant comics from xkcd and posts them as a onebox';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'xkcd')];
    }
}
