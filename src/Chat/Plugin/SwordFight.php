<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Conversation;
use Room11\Jeeves\Chat\Command\Message;

class SwordFight implements Plugin
{
    const COMMAND = 'swordfight';

    private $chatClient;

    // We only match on insults for now because comparing text is actually pretty hard and I'm lazy
    private $matches = [
        [
            'insult'   => [
                'text'        => 'You fight like a Dairy Farmer!',
                'maxDistance' => 15,
            ],
            'response' => [
                'text'        => 'How appropriate! You fight like a cow!',
                'maxDistance' => 20,
            ],
        ],
        [
            'insult'   => [
                'text'        => 'This is the END for you, you gutter crawling cur!',
                'maxDistance' => 32,
            ],
            'response' => [
                'text'        => 'And I\'ve got a little TIP for you, get the POINT?',
                'maxDistance' => 25,
            ],
        ],
    ];

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
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
        return $message instanceof Conversation && $this->isMatch($message);
    }

    private function isMatch(Message $message): bool
    {
        $text = $this->normalize($message->getText());

        foreach ($this->matches as $match) {
            if ($this->textDoesMatch($match['insult'], $text)) {
                return true;
            }

            if ($this->textDoesMatch($match['response'], $text)) {
                return true;
            }
        }

        return false;
    }

    private function textDoesMatch(array $pattern, string $text): bool
    {
        return levenshtein($this->normalize($pattern['text']), $text) <= $pattern['maxDistance'];
    }

    private function normalize(string $text): string
    {
        $text = preg_replace('/@[^\b\s]+/', '', $text);
        $text = preg_replace('/[^a-z0-9 ]/', ' ', strtolower($text));

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function getResult(Message $message): \Generator
    {
        yield from $this->chatClient->postMessage(
            sprintf(':%s %s', $message->getOrigin(), $this->getResponse($message))
        );
    }

    private function getResponse(Message $message): string
    {
        $text = $this->normalize($message->getText());

        foreach ($this->matches as $match) {
            if ($this->textDoesMatch($match['insult'], $text)) {
                return $match['response']['text'];
            }
        }

        return null;
    }
}
