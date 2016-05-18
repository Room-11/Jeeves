<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Conversation;
use Room11\Jeeves\Chat\Message\Message;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\NoCommands;
use Room11\Jeeves\Chat\Plugin\Traits\NoDisableEnable;
use Room11\Jeeves\Chat\Plugin\Traits\NoEventHandlers;

class SwordFight implements Plugin
{
    use NoCommands, NoEventHandlers, NoDisableEnable;

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

    private function isMatch(Conversation $conversation): bool
    {
        $text = $this->normalize($conversation->getText());

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

    private function getResponse(Conversation $conversation): string
    {
        $text = $this->normalize($conversation->getText());

        foreach ($this->matches as $match) {
            if ($this->textDoesMatch($match['insult'], $text)) {
                return $match['response']['text'];
            }
        }

        return null;
    }

    public function handleMessage(Message $message): Promise
    {
        return $message instanceof Conversation && $this->isMatch($message)
            ? $this->chatClient->postReply($message, $this->getResponse($message))
            : new Success();
    }

    public function getName(): string
    {
        return 'SwordFight';
    }

    public function getDescription(): string
    {
        return 'Trades insults in conversation';
    }

    public function getHelpText(array $args): string
    {
        // TODO: Implement getHelpText() method.
    }

    /**
     * @return callable|null
     */
    public function getMessageHandler()
    {
        return [$this, 'handleMessage'];
    }
}
