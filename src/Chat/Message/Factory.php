<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\MentionMessage;
use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Event\ReplyMessage;

class Factory
{
    public function build(MessageEvent $event): Message
    {
        if (strpos($event->getMessageContent()->textContent, '!!') === 0) {
            return new Command($event, $event->getRoom());
        }

        if ($event instanceof MentionMessage || $event instanceof ReplyMessage) {
            return new Conversation($event, $event->getRoom());
        }

        return new Message($event, $event->getRoom());
    }
}
