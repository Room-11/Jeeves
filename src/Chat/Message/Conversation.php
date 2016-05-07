<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\MentionMessage;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class Conversation extends Message
{
    public function __construct(MentionMessage $event, ChatRoom $room)
    {
        parent::__construct($event, $room);
    }

    public function getParentMessageId()
    {
        /** @var MentionMessage $event */
        $event = $this->getEvent();
        return $event->getParentId();
    }
}
