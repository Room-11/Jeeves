<?php declare(strict_types = 1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

interface KeyValue
{
    /**
     * Get the data partition name that this key value store accesses
     *
     * @return string
     */
    public function getPartitionName(): string;

    /**
     * Determine whether a key exists in the data store
     *
     * @param string $key
     * @param ChatRoom|null $room
     * @return Promise<bool>
     */
    public function exists(string $key, ChatRoom $room = null): Promise;

    /**
     * Get the value from the data store for the specified key
     *
     * @param string $key
     * @param ChatRoom|null $room
     * @return Promise<mixed>
     * @throws \LogicException when the specified key does not exist
     */
    public function get(string $key, ChatRoom $room = null): Promise;


    /**
     * Set the value in the data store for the specified key
     *
     * @param string $key
     * @param mixed $value
     * @param ChatRoom|null $room
     * @return Promise<void>
     */
    public function set(string $key, $value, ChatRoom $room = null): Promise;

    /**
     * Remove the value from the data store for the specified key
     *
     * @param string $key
     * @param ChatRoom|null $room
     * @throws \LogicException when the specified key does not exist
     * @return Promise<void>
     */
    public function unset(string $key, ChatRoom $room = null): Promise;
}
