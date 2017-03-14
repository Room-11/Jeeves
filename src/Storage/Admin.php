<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

interface Admin
{
    function getAll(ChatRoom $room): Promise;

    function isAdmin(ChatRoom $room, int $userId): Promise;

    function add(ChatRoom $room, int $userId): Promise;

    function remove(ChatRoom $room, int $userId): Promise;
}
