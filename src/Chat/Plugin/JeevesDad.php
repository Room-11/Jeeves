<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Message;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\AutoName;
use Room11\Jeeves\Chat\Plugin\Traits\Helpless;
use Room11\Jeeves\Chat\Plugin\Traits\NoCommands;
use Room11\Jeeves\Chat\Plugin\Traits\NoDisableEnable;
use Room11\Jeeves\Chat\Plugin\Traits\NoEventHandlers;

class JeevesDad implements Plugin
{
    use NoCommands, NoEventHandlers, NoDisableEnable, AutoName, Helpless;

    const FREQUENCY = 10;

    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function handleMessage(Message $message)
    {
        if (!preg_match('#(?:i\'m|i am)\s+(.+?)\s*(?:[.,!]|$)#i', $message->getText(), $match)) {
            return;
        }

        if (random_int(1, self::FREQUENCY) !== 1) {
            return;
        }

        $fullName = strtoupper(substr($match[1], 0, 1)) . substr($match[1], 1);

        $reply = sprintf('Hello %s. I am %s.', $fullName, $message->getRoom()->getSessionInfo()->getUser()->getName());

        if (preg_match('#^(\S+)\s+\S#', $fullName, $match)) {
            $reply .= sprintf(' Do you mind if I just call you %s?', $match[1]);
        }

        yield $this->chatClient->postReply($message, $reply);
    }

    public function getDescription(): string
    {
        return 'Is really annoying';
    }

    /**
     * @return callable|null
     */
    public function getMessageHandler() /* : ?callable */
    {
        return [$this, 'handleMessage'];
    }
}
