<?php

namespace Room11\Jeeves\Plugin\Traits;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;

trait NoDisable
{
    public function disableForRoom(ChatRoom $room, bool $persist) {}
}
