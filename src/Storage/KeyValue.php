<?php declare(strict_types = 1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\StackChat\Room\Room as ChatRoom;

interface KeyValue
{
    /**
     * Get the data partition name that this key value store accesses
     *
     * @return string
     */
    function getPartitionName(): string;

    /**
     * Determine whether a key exists in the data store
     *
     * @param ChatRoom|null $room
     * @return Promise<bool>
     */
    function clear(ChatRoom $room = null): Promise;

    /**
     * Determine whether a key exists in the data store
     *
     * @param string $key
     * @param ChatRoom|null $room
     * @return Promise<bool>
     */
    function exists(string $key, ChatRoom $room = null): Promise;

    /**
     * Get the value from the data store for the specified key
     *
     * @param ChatRoom|null $room
     * @return Promise<mixed>
     * @throws \LogicException when the specified key does not exist
     */
    function getAll(ChatRoom $room = null): Promise;

    /**
     * Get the value from the data store for the specified key
     *
     * @param ChatRoom|null $room
     * @return Promise<mixed>
     * @throws \LogicException when the specified key does not exist
     */
    function getKeys(ChatRoom $room = null): Promise;

    /**
     * Get the value from the data store for the specified key
     *
     * @param string $key
     * @param ChatRoom|null $room
     * @return Promise<mixed>
     * @throws \LogicException when the specified key does not exist
     */
    function get(string $key, ChatRoom $room = null): Promise;

    /**
     * Set the value in the data store for the specified key
     *
     * @param string $key
     * @param mixed $value
     * @param ChatRoom|null $room
     * @return Promise<void>
     */
    function set(string $key, $value, ChatRoom $room = null): Promise;

    /**
     * Remove the value from the data store for the specified key
     *
     * @param string $key
     * @param ChatRoom|null $room
     * @throws \LogicException when the specified key does not exist
     * @return Promise<void>
     */
    function unset(string $key, ChatRoom $room = null): Promise;
}
