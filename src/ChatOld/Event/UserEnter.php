<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Event\Traits\RoomSource;
use Room11\Jeeves\Chat\Event\Traits\UserSource;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class UserEnter extends BaseEvent implements RoomSourcedEvent, UserSourcedEvent
{
    use RoomSource, UserSource;

    const TYPE_ID = EventType::USER_JOINED;

    public function __construct(array $data, ChatRoom $room)
    {
        parent::__construct($data, $room->getIdentifier()->getHost());

        $this->room = $room;

        $this->userId   = $data['user_id'];
        $this->userName = $data['user_name'];
    }
}
