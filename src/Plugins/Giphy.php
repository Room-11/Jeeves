<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Success;
use Room11\StackChat\Client\Client;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;

class Giphy extends BasePlugin
{
    private const API_BASE_URL = 'http://api.giphy.com/';
    private const PUBLIC_BETA_API_KEY = 'dc6zaTOxFJmzC';

    private const RATING_Y = 'y';
    private const RATING_G = 'g';
    private const RATING_PG = 'pg';
    private const RATING_PG13 = 'pg-13';
    private const RATING_R = 'r';

    private const VALID_RATINGS = [
        self::RATING_Y,
        self::RATING_G,
        self::RATING_PG,
        self::RATING_PG13,
        self::RATING_R
    ];

    private $chatClient;
    private $httpClient;
    private $apiKey;
    private $rating;

    public function __construct(
        Client $chatClient,
        HttpClient $httpClient,
        $apiKey = self::PUBLIC_BETA_API_KEY,
        $rating = self::RATING_PG13
    ) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
        $this->setRating($rating);
    }

    private function setRating($rating) /*: void*/
    {
        if (!in_array($rating, self::VALID_RATINGS)) {
            throw new \DomainException(
                sprintf(
                    'Rating must be one of %s. Got %s',
                    implode(', ', self::VALID_RATINGS),
                    $rating
                )
            );
        }

        $this->rating = $rating;
    }

    private function getMessage(array $result): string
    {
        return empty($result['data'])
            ? 'Very iffy! Jeeves found no giphy :('
            : $result['data']['image_url'];
    }

    public function random(Command $command)
    {
        if (!$command->hasParameters()) {
            return new Success();
        }

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            self::API_BASE_URL . 'v1/gifs/random?' . http_build_query([
                'api_key' => $this->apiKey,
                'rating' => $this->rating,
                'tag' => implode(' ', $command->getParameters())
            ])
        );

        $result = json_decode($response->getBody(), true);

        return $this->chatClient->postMessage($command, $this->getMessage($result));
    }

    public function getDescription(): string
    {
        return 'Gets random gifs from Giphy and displays them in oneboxes';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Random', [$this, 'random'], 'giphy')];
    }
}
