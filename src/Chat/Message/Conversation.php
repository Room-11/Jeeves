<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\MentionMessage;

class Conversation extends Message
{
    public function __construct(MentionMessage $event)
    {
        parent::__construct($event);
    }

    public function getParentMessageId()
    {
        /** @var MentionMessage $event */
        $event = $this->getEvent();
        return $event->getParentId();
    }
}
