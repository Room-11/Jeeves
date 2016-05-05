<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;

interface RoomSourcedEvent
{
    public function getRoom(): ChatRoom;
}
