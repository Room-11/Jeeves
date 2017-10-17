<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\StackChat\Room\Room as ChatRoom;

interface Ban
{
    /**
     * @param ChatRoom $room
     * @return Promise<array>
     */
    function getAll(ChatRoom $room): Promise;

    /**
     * @param ChatRoom $room
     * @param int $userId
     * @return Promise<bool>
     */
    function isBanned(ChatRoom $room, int $userId): Promise;

    /**
     * @param ChatRoom $room
     * @param int $userId
     * @param string $duration
     * @return Promise<void>
     */
    function add(ChatRoom $room, int $userId, string $duration): Promise;

    /**
     * @param ChatRoom $room
     * @param int $userId
     * @return Promise<void>
     */
    function remove(ChatRoom $room, int $userId): Promise;
}
