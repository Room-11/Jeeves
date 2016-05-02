<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

interface RoomSourcedEvent
{
    public function getRoomId(): int;
}
