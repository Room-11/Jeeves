<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Event\Traits\RoomSource;
use Room11\Jeeves\Chat\Event\Traits\UserSource;

class UserEnter extends Event implements RoomSourcedEvent, UserSourcedEvent
{
    use RoomSource;
    use UserSource;

    const EVENT_TYPE_ID = 3;

    public function __construct(array $data)
    {
        parent::__construct((int)$data['id'], (int)$data['time_stamp']);

        $this->roomId   = $data['room_id'];

        $this->userId   = $data['user_id'];
        $this->userName = $data['user_name'];
    }
}
