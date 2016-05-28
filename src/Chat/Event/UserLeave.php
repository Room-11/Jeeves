<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Event\Traits\RoomSource;
use Room11\Jeeves\Chat\Event\Traits\UserSource;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class UserLeave extends BaseEvent implements RoomSourcedEvent, UserSourcedEvent
{
    use RoomSource;
    use UserSource;

    const TYPE_ID = 4;

    public function __construct(array $data, ChatRoom $room)
    {
        parent::__construct($data);

        $this->room     = $room;

        $this->userId   = $data['user_id'];
        $this->userName = $data['user_name'];
    }
}
