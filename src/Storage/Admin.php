<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

interface Admin
{
    public function getAll(ChatRoom $room): Promise;

    public function isAdmin(ChatRoom $room, int $userId): Promise;

    public function add(ChatRoom $room, int $userId): Promise;

    public function remove(ChatRoom $room, int $userId): Promise;
}
