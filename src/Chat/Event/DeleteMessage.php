<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class DeleteMessage extends MessageEvent
{
    const TYPE_ID = 10;

    public function __construct(array $data, ChatRoom $room)
    {
        parent::__construct($data, $room);
    }
}
