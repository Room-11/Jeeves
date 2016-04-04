<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;

class Giphy implements Plugin
{
    const COMMAND = 'giphy';
    const PUBLIC_BETA_API_KEY = 'dc6zaTOxFJmzC';

    const RATING_Y = 'y';
    const RATING_G = 'g';
    const RATING_PG = 'pg';
    const RATING_PG13 = 'pg-13';
    const RATING_R = 'r';

    const VALID_RATINGS = [
        self::RATING_Y,
        self::RATING_G,
        self::RATING_PG,
        self::RATING_PG13,
        self::RATING_R
    ];

    private $chatClient;
    private $apiKey;
    private $rating;

    public function __construct(
        ChatClient $chatClient,
        $apiKey = self::PUBLIC_BETA_API_KEY,
        $rating = self::RATING_PG13
    ) {
        $this->chatClient = $chatClient;
        $this->apiKey = $apiKey;
        $this->setRating($rating);
    }

    private function setRating($rating): void
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

    public function handle(Message $message): \Generator
    {
        if (!$this->validMessage($message)) {
            return;
        }

        yield from $this->getResult($message);
    }

    private function validMessage(Message $message): bool
    {
        return $message instanceof Command
            && $message->getCommand() === self::COMMAND
            && $message->getParameters();
    }

    private function getResult(Message $message): \Generator
    {
        $response = yield from $this->chatClient->request(
            'http://api.giphy.com/v1/gifs/random?' . http_build_query([
                'api_key' => $this->apiKey,
                'rating' => $this->rating,
                'tag' => implode('%20', $message->getParameters())
            ])
        );

        $result = json_decode($response->getBody(), true);

        yield from $this->chatClient->postMessage($this->getMessage($result));
    }

    private function getMessage(array $result): string
    {
        return empty($result['data'])
            ? 'Very iffy! Jeeves found no giphy :('
            : $result['data']['image_url'];
    }
}
