<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

interface Ban
{
    /**
     * @param ChatRoom $room
     * @return Promise<array>
     */
    public function getAll(ChatRoom $room): Promise;

    /**
     * @param ChatRoom $room
     * @param int $userId
     * @return Promise<bool>
     */
    public function isBanned(ChatRoom $room, int $userId): Promise;

    /**
     * @param ChatRoom $room
     * @param int $userId
     * @param string $duration
     * @return Promise<void>
     */
    public function add(ChatRoom $room, int $userId, string $duration): Promise;

    /**
     * @param ChatRoom $room
     * @param int $userId
     * @return Promise<void>
     */
    public function remove(ChatRoom $room, int $userId): Promise;
}
