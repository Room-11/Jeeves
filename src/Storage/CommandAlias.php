<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\StackChat\Room\Room as ChatRoom;

interface CommandAlias
{
    function getAll(ChatRoom $room): Promise;

    function add(ChatRoom $room, string $command, string $mapping): Promise;

    function remove(ChatRoom $room, string $command): Promise;

    function exists(ChatRoom $room, string $command): Promise;

    function get(ChatRoom $room, string $command): Promise;
}
