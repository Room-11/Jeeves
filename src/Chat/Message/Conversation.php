<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class Conversation extends Message
{
    public function __construct(MessageEvent $event, ChatRoom $room)
    {
        parent::__construct($event, $room);
    }

    public function getParentMessageId()
    {
        return $this->getEvent()->getParentId();
    }
}
