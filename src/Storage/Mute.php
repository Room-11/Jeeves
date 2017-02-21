<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;

interface Mute
{
    /**
     * Fetch a list of all rooms Jeeves is specifically silenced in.
     * @return Promise<array>
     */
    public function getAll(): Promise;

    /**
     * Check if Jeeves is muted in a given room.
     * @param ChatRoomIdentifier $room
     * @return Promise<bool>
     */
    public function isMuted(ChatRoomIdentifier $room): Promise;

    /**
     * Add a room to the mute list.
     * @param ChatRoomIdentifier $room
     * @return Promise<void>
     */
    public function add(ChatRoomIdentifier $room): Promise;

    /**
     * Remove a room from the mute list.
     * @param ChatRoomIdentifier $room
     * @return Promise<void>
     */
    public function remove(ChatRoomIdentifier $room): Promise;
}
