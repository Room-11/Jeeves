<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Amp\Success;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Entities\ChatMessage;

class Stahp extends BasePlugin
{
    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function handleMessage(ChatMessage $message): Promise
    {
        return \preg_match('#^stop|stahp$#i', $message->getText(), $match)
            ? $this->chatClient->postReply($message, "HAMMERTIME!")
            : new Success;
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
