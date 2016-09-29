<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\MessageEvent;

class Factory
{
    private function isCommandMessage(MessageEvent $event)
    {
        return strpos($event->getMessageContent()->textContent, '!!') === 0;
    }

    public function build(MessageEvent $event): Message
    {
        return $this->isCommandMessage($event)
            ? new Command($event, $event->getRoom())
            : new Message($event, $event->getRoom());
    }
}
