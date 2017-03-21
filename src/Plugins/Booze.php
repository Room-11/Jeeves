<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\Jeeves\Utf8Chars;
use Room11\StackChat\Client\Client;

class Booze extends BasePlugin
{
    private const SEARCH_URL = 'https://distiller.com/api/v1/spirits/search?term=%s';

    private const DETAIL_URL = 'https://distiller.com/api/v1/spirits/%s.json';

    private const DETAIL_WEB_URL = 'https://distiller.com/spirits/%s';

    private $chatClient;

    private $httpClient;

    public function __construct(Client $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function searchDrink($keywords): Promise
    {
        return \Amp\resolve(function() use ($keywords) {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request(
                sprintf(self::SEARCH_URL, rawurlencode($keywords))
            );

            return json_try_decode($response->getBody(), true);
        });
    }

    private function getUserRatings(array $spirits): Promise
    {
        return \Amp\resolve(function() use ($spirits) {
            $requests = [];

            foreach ($spirits as $index => $spirit) {
                $requests[$index] = sprintf(self::DETAIL_URL, $spirit['slug']);
            }

            $promises = $this->httpClient->requestMulti($requests);

            $responses = yield \Amp\all($promises);

            $userRatings = [];

            foreach ($responses as $key => $response) {
                /** @var HttpResponse $response */
                $detailPage = json_try_decode($response->getBody(), true);

                $userRatings[$key] = $detailPage['average_rating'];
            }

            return $userRatings;
        });
    }

    private function getUserRatingMessage($userRating): string
    {
        if ($userRating === null) {
            return '';
        }

        $message = ' - User Rating: ' . $userRating . ' ';
        $message.= str_repeat('★', (int) round($userRating));
        $message.= str_repeat('☆', 5 - (int) round($userRating));

        return $message;
    }

    private function buildMessage($keywords, array $spirits, array $userRatings): string
    {
        $message = sprintf('Search for "%s" (%s results)', $keywords, count($spirits));

        foreach ($spirits as $index => $spirit) {
            $message .= sprintf(
                "\n%s %s %s %s (%s)",
                Utf8Chars::BULLET,
                $spirit['name'],
                $spirit['expert_rating'] ? ' - Expert Rating: ' . $spirit['expert_rating'] : '',
                $this->getUserRatingMessage($userRatings[$index]),
                sprintf(self::DETAIL_WEB_URL, $spirit['slug'])
            );
        }

        return $message;
    }

    public function findBooze(Command $command)
    {
        try {
            $searchResults = yield $this->searchDrink($command->getCommandText());
        } catch (\Throwable $e) {
            return $this->chatClient->postReply(
                $command, "You've had enough already. Also something went wrong trying to find your drink."
            );
        }

        if (!count($searchResults['spirits'])) {
            return $this->chatClient->postReply(
                $command, 'No results for: ' . $command->getCommandText()
            );
        }

        try {
            $userRatings = yield $this->getUserRatings($searchResults['spirits']);
        } catch (\Throwable $e) {
            return $this->chatClient->postReply(
                $command, "You've had enough already. Also something went wrong trying to fetch the user ratings."
            );
        }

        return $this->chatClient->postReply(
            $command,
            $this->buildMessage($command->getCommandText(), $searchResults['spirits'], $userRatings)
        );
    }

    public function getName(): string
    {
        return 'Booze';
    }

    public function getDescription(): string
    {
        return 'Finds information about drinks';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint('Booze', [$this, 'findBooze'], 'booze', 'Finds information about drinks'),
        ];
    }
}
