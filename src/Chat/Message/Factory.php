<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Event\ReplyMessage;

class Factory
{
    private function isCommandMessage(MessageEvent $event)
    {
        return strpos($event->getMessageContent()->textContent, '!!') === 0;
    }

    private function isConversationMessage(MessageEvent $event)
    {
        if ($event instanceof ReplyMessage) {
            return true;
        }

        $expr = '#(?:^|\s)@' . preg_quote($event->getRoom()->getSessionInfo()->getUser()->getName(), '#') . '(?:\s|$)#i';
        $text = $event->getMessageContent()->textContent;

        return (bool)preg_match($expr, $text);
    }

    public function build(MessageEvent $event): Message
    {
        if ($this->isCommandMessage($event)) {
            return new Command($event, $event->getRoom());
        }

        if ($this->isConversationMessage($event)) {
            return new Conversation($event, $event->getRoom());
        }

        return new Message($event, $event->getRoom());
    }
}
