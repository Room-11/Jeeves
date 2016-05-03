<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\MentionMessage;
use Room11\Jeeves\Chat\Event\MessageEvent;

class Factory
{
    public function build(MessageEvent $message): Message
    {
        if (strpos($message->getMessageContent(), '!!') === 0) {
            return new Command($message);
        }

        if ($message instanceof MentionMessage) {
            return new Conversation($message);
        }

        return new Message($message);
    }
}
