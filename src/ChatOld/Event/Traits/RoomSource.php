<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event\Traits;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;

trait RoomSource
{
    private $room;

    public function getRoom(): ChatRoom
    {
        return $this->room;
    }
}
