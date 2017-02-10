<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Message;

class Stahp extends BasePlugin
{
    private $chatClient;
    
    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function handleMessage(Message $message)
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
