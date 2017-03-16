<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\StackChat\Client\Chars;
use Room11\StackChat\Client\Client;
use Room11\StackChat\Entities\PostedMessage;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;

class Imdb extends BasePlugin
{
    private const OMDB_API_ENDPOINT = 'https://www.omdbapi.com/';

    private $chatClient;
    private $httpClient;

    public function __construct(Client $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    public function search(Command $command)
    {
        if (!$command->hasParameters()) {
            return $this->chatClient->postReply(
                $command,
                /** @lang text */ 'Mhm, I need a film title you want me to look for. (Usage: !!imdb film-title)'
            );
        }

        $search = $command->getText();

        $message = yield $this->chatClient->postMessage(
            $command,
            sprintf("_Looking for '%s' for you%s_", $search, Chars::ELLIPSIS)
        );

        $params = $this->buildTitleSearchParams($search);

        // Send it out.
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            sprintf('%s?%s', self::OMDB_API_ENDPOINT, http_build_query($params))
        );

        if ($response->getStatus() !== 200) {
            return $this->chatClient->editMessage(
                $message,
                sprintf(
                    "Sorry, the [OMDB API](https://www.omdbapi.com) is currently unavailable. (%d)",
                    $response->getStatus()
                )
            );
        }

        /** @var \stdClass $data */
        $data = @json_decode($response->getBody());

        if (!$data || $data->Response === 'False') {
            return $this->chatClient->editMessage(
                $message,
                sprintf("Sorry, I couldn't find anything like '%s'.", $search)
            );
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
        list(, $responses) = yield $allRequests;

        $descriptionResults = [];
        foreach ($responses as $key => $response) {
            $responseBody = @json_decode($response->getBody());
            if (!$responseBody || $responseBody->Response === 'False') {
                continue;
            }

            $descriptionResults[$key] = $responseBody;
        }

        return $this->chatClient->editMessage(
            $message,
            $this->formatSearchResults($searchResults, $descriptionResults)
        );
    }

    /**
     * Build an array of query string parameters to search for a given title using the OMDB API.
     * @param string $searchString
     * @return array
     */
    private function buildTitleSearchParams(string $searchString): array
    {
        $params = [
            's' => $searchString,
            'r' => 'json',
        ];

        return $params;
    }

    /**
     * Takes data and mushes it into a human friendly multi-line string meant as an output.
     * The first array should be the resulting response from the OMDB API search request.
     * The second array should be a collection of individual OMDBI title lookup results, indexed by imdb ID.
     * @param array $searchResults
     * @param array|null $deepResults
     * @return string
     */
    private function formatSearchResults(array $searchResults, array $deepResults = null): string
    {
        $outputLines = [];

        foreach ($searchResults as $id => $searchResult) {
            $description = '';

            if (is_array($deepResults) && isset($deepResults[$id])) {
                $description = $this->formatAdditionalTitleInformation($deepResults[$id]);
            }

            $outputLines[] = sprintf(
                '%s %s (%d) [ %s ]%s',
                Chars::BULLET,
                $searchResult->Title,
                $searchResult->Year,
                $this->getImdbUrlById($searchResult->imdbID),
                $description
            );
        }

        return implode("\n", $outputLines);
    }

    /**
     * Takes a single search by ID response from the OMDB API and spits out a
     * string of descriptive data about the title.
     * @param \stdClass $result
     * @return string
     */
    private function formatAdditionalTitleInformation($result): string
    {
        $output = '';

        $append = [];

        // Film Plot
        if ($result->Plot !== 'N/A') {
            $append[] = $this->truncate($result->Plot, 75);
        }

        // Ratings
        $ratings = $this->fetchRatings($result);

        if (count($ratings) > 0) {
            $ratings = array_map([$this, 'formatRating'], $ratings);
            $append[] = '[' . implode(' | ', $ratings) . ']';
        }

        if (count($append) > 0) {
            $output = ' - ' . implode(' ', $append);
        }

        return $output;
    }

    /**
     * Simple string truncation. Trims before and during truncation if required.
     * @param string $string
     * @param int $length
     * @return string
     */
    private function truncate(string $string, int $length): string
    {
        $string = trim($string);
        if (strlen($string) > $length) {
            $string = rtrim(substr($string, 0, $length), '. ') . '…';
        }
        return $string;
    }

    /**
     * Takes a single OMDB API title response and plucks out the relevant ratings
     * with their cute accompanying symbols.
     * @param \stdClass $result
     * @return array
     */
    private function fetchRatings(\stdClass $result): array
    {
        $output = [];

        // IMDB Film Rating

        if ($this->hasData($result->imdbRating)) {
            $output[] = [
                'symbol' => '♥',
                'rating' => $result->imdbRating,
            ];
        }

        // Rotten Tomatoes Film Rating

        if ($this->hasData($result->tomatoRating)) {
            $output[] = [
                'symbol' => '🍅',
                'rating' => $result->tomatoRating
            ];
        }

        return $output;
    }

    /**
     * Check whether a string retrieved from the OMDB API has any meaningful data.
     * @param string $string
     * @return bool
     */
    private function hasData(string $string): bool
    {
        return $string !== 'N/A';
    }

    /**
     * Generate the main IMDB url for a title based on its IMDB id.
     * @param string $id
     * @return string
     */
    private function getImdbUrlById(string $id): string
    {
        return sprintf('http://www.imdb.com/title/%s/', $id);
    }

    /**
     * Build an array of query string parameters to fetch details for a specific title
     * using the OMDB API.
     * @param string $id
     * @return array
     */
    private function buildTitleDescParams(string $id): array
    {
        $params = [
            'i' => $id,
            'r' => 'json',
            'tomatoes' => 'true' // Required for rotten tomatoes reviews.
        ];

        return $params;
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Searches and displays IMDB entries';
    }

    /**
     * @inheritDoc
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'imdb')];
    }

    /**
     * Takes a given rating and formats it into a friendly string.
     * @param array $rating
     * @return string
     */
    private function formatRating(array $rating): string
    {
        return $rating['symbol'] . ' ' . $rating['rating'];
    }
}
