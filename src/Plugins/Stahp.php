<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Entities\ChatMessage;

class Stahp extends BasePlugin
{
    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function handleMessage(ChatMessage $message)
    {
        if (preg_match('#\bstahp\b#i', $message->getText(), $match)) {
            yield $this->chatClient->postReply($message, "HAMMERTIME!");
        }
    }

    public function getDescription(): string
    {
        return 'Can\'t touch this.';
    }
    /**
     * @return callable|null
     */
    public function getMessageHandler() /* : ?callable */
    {
        return [$this, 'handleMessage'];
    }
}
