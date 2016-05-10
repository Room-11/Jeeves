<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

interface Admin
{
    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @return Promise
     */
    public function getAll($room): Promise;

    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @param int $userId
     * @return Promise
     */
    public function isAdmin($room, int $userId): Promise;

    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @param int $userId
     * @return Promise
     */
    public function add($room, int $userId): Promise;

    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @param int $userId
     * @return Promise
     */
    public function remove($room, int $userId): Promise;
}
