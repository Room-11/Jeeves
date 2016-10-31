<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Success;
use Ds\Queue;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Entities\PostedMessage;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Room11\DOMUtils\domdocument_load_html;

class Imdb extends BasePlugin
{
    const OMDB_API_ENDPOINT = 'http://www.omdbapi.com/';
    private $chatClient;
    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    public function search(Command $command): \Generator
    {
        if (!$command->hasParameters()) {
            // TODO: Usage instead?
            return new Success();
        }

        $search = $command->getText();

        $message = yield $this->chatClient->postMessage(
            $command->getRoom(),
            sprintf('_Looking for \'%s\' for you…_', $search)
        );

        $params = $this->buildTitleSearchParams($search);

        // Send it out.
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            sprintf('%s?%s', self::OMDB_API_ENDPOINT, http_build_query($params))
        );

        /** @var \stdClass $data */
        $data = @json_decode($response->getBody());

        if (!$data || $data->Response === 'False') {
            return $this->chatClient->postMessage($command->getRoom(), 'I couldn\'t find anything for that title.');
        }

        $searchResults = [];
        foreach ($data->Search as $searchResult) {
            $searchResults[$searchResult->imdbID] = $searchResult;
        }

        // Only pick the top 3 results if needed.
        $searchResults = array_slice($searchResults, 0, 3, true);

        /** @var PostedMessage $chatMessage */
        yield $this->chatClient->editMessage(
            $message,
            $this->formatSearchResults($searchResults)
        );

        $requests = [];
        // Send out multiple http requests to get film descriptions and ratings.
        foreach ($searchResults as $id => $searchResult) {
            $requests[$id] = sprintf(
                '%s?%s',
                self::OMDB_API_ENDPOINT,
                http_build_query($this->buildTitleDescParams($id))
            );
        }

        // Wait until all requests are done, allow failures.
        $allRequests = \Amp\some($this->httpClient->requestMulti($requests));
        list($errors, $responses) = yield $allRequests;

        $descriptionResults = [];
        foreach ($responses as $key => $response)
        {
            $responseBody = @json_decode($response->getBody());
            if(!$responseBody || $responseBody->Response === 'False') {
                continue;
            }

            $descriptionResults[$key] = $responseBody;
        }

        return $this->chatClient->editMessage(
            $message,
            $this->formatSearchResults($searchResults, $descriptionResults)
        );

    }

    private function buildTitleSearchParams(string $searchString): array
    {
        $params = [
            's' => $searchString,
            'r' => 'json',
            'type' => 'movie'
        ];

        return $params;
    }

    private function formatSearchResults(array $searchResults, array $deepResults = null) : string
    {
        $outputLines = [];

        foreach ($searchResults as $id => $searchResult)
        {
            $description = '';
            if(is_array($deepResults) && isset($deepResults[$id])) {
                $deepResult = $deepResults[$id];

                $ratings = [];

                $append = [];

                // Film Plot

                if ($deepResult->Plot !== 'N/A') {
                    $append[] = $this->truncate($deepResult->Plot, 75);
                }

                // Ratings

                if($deepResult->imdbRating !== 'N/A') {
                    $ratings[] = '♥ ' . $deepResult->imdbRating;
                }

                if ($deepResult->tomatoRating !== 'N/A') {
                    $ratings[] = '🍅 ' . $deepResult->tomatoRating;
                }

                // Mush it all together.
                if (count($ratings) > 0) {
                    $append[] = '[' . implode(' | ', $ratings) . ']';
                }

                if (count($append) > 0) {
                    $description = ' - ' . implode(' ', $append);
                }
            }
            $outputLines[] = sprintf(
                '%s (%d) [ %s ]%s',
                $searchResult->Title,
                $searchResult->Year,
                $this->getImdbUrlById($searchResult->imdbID),
                $description
            );
        }

        return implode("\n", $outputLines);
    }

    private function truncate(string $string, int $length): string
    {
        $string = trim($string);
        if(strlen($string) > $length) {
            $string = rtrim(substr($string, 0, $length), '. ') . '…';
        }
        return $string;
    }

    private function getImdbUrlById(string $id)
    {
        return sprintf('http://www.imdb.com/title/%s/', $id);
    }

    private function buildTitleDescParams(string $id): array
    {
        $params = [
            'i' => $id,
            'r' => 'json',
            'tomatoes' => 'true'
        ];

        return $params;
    }

    public function getDescription(): string
    {
        return 'Searches and displays IMDB entries';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'imdb')];
    }

    private function getMessage(HttpResponse $response): string
    {
        $dom = domdocument_load_html($response->getBody());

        if ($dom->getElementsByTagName('resultset')->length === 0) {
            return 'I cannot find that title.';
        }

        /** @var \DOMElement $result */
        $result = $dom->getElementsByTagName('imdbentity')->item(0);
        /** @var \DOMText $titleNode */
        $titleNode = $result->firstChild;

        return sprintf(
            '[ [%s](%s) ] %s',
            $titleNode->wholeText,
            'http://www.imdb.com/title/' . $result->getAttribute('id'),
            $result->getElementsByTagName('description')->item(0)->textContent
        );
    }
}
