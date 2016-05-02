<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event\Traits;

trait RoomSource
{
    private $roomId;

    public function getRoomId(): int
    {
        return $this->roomId;
    }
}
