<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;

interface ChatRoomContainer
{
    function getRoom(): ChatRoom;
}
