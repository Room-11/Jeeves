<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\MentionMessage;
use Room11\Jeeves\Chat\Event\MessageEvent;

class Factory
{
    public function build(MessageEvent $event): Message
    {
        if (strpos($event->getMessageContent(), '!!') === 0) {
            return new Command($event, $event->getRoom());
        }

        if ($event instanceof MentionMessage) {
            return new Conversation($event, $event->getRoom());
        }

        return new Message($event, $event->getRoom());
    }
}
