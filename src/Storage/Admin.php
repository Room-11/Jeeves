<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage;

use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

interface Admin
{
    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @return \Generator
     */
    public function getAll($room): \Generator;

    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @param int $userId
     * @return \Generator
     */
    public function isAdmin($room, int $userId): \Generator;

    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @param int $userId
     * @return \Generator
     */
    public function add($room, int $userId): \Generator;

    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @param int $userId
     * @return \Generator
     */
    public function remove($room, int $userId): \Generator;
}
