<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

interface CommandAlias
{
    public function getAll(ChatRoom $room): Promise;

    public function add(ChatRoom $room, string $command, string $mapping): Promise;

    public function remove(ChatRoom $room, string $command): Promise;

    public function exists(ChatRoom $room, string $command): Promise;

    public function get(ChatRoom $room, string $command): Promise;
}
