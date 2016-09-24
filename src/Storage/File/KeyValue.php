<?php declare(strict_types = 1);

namespace Room11\Jeeves\Storage\File;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\KeyValue as KeyValueStorage;
use function Amp\resolve;

class KeyValue implements KeyValueStorage
{
    private $accessor;
    private $dataFileTemplate;
    private $partitionName;

    public function __construct(JsonFileAccessor $accessor, string $dataFile, string $partitionName)
    {
        $this->accessor = $accessor;
        $this->dataFileTemplate = $dataFile;
        $this->partitionName = $partitionName;
    }

    /**
     * Get the data partition name that this key value store accesses
     *
     * @return string
     */
    public function getPartitionName(): string
    {
        return $this->partitionName;
    }

    /**
     * Determine whether a key exists in the data store
     *
     * @param string $key
     * @param ChatRoom|null $room
     * @return Promise<bool>
     */
    public function exists(string $key, ChatRoom $room = null): Promise
    {
        return resolve(function() use($key, $room) {
            $data = yield $this->accessor->read($this->dataFileTemplate, $room);
            return array_key_exists($key, $data[$this->partitionName] ?? []);
        });
    }

    /**
     * Get the value from the data store for the specified key
     *
     * @param string $key
     * @param ChatRoom|null $room
     * @return Promise<mixed>
     * @throws \LogicException when the specified key does not exist
     */
    public function get(string $key, ChatRoom $room = null): Promise
    {
        return resolve(function() use($key, $room) {
            $data = yield $this->accessor->read($this->dataFileTemplate, $room);

            if (!array_key_exists($key, $data[$this->partitionName] ?? [])) {
                throw new \LogicException("Undefined key '{$key}'");
            }

            return $data[$this->partitionName][$key];
        });
    }

    /**
     * Set the value in the data store for the specified key
     *
     * @param string $key
     * @param mixed $value
     * @param ChatRoom|null $room
     * @return Promise<void>
     */
    public function set(string $key, $value, ChatRoom $room = null): Promise
    {
        return $this->accessor->writeCallback(function($data) use($key, $value) {
            $data[$this->partitionName][$key] = $value;
            return $data;
        }, $this->dataFileTemplate, $room);
    }

    /**
     * Remove the value from the data store for the specified key
     *
     * @param string $key
     * @param ChatRoom|null $room
     * @throws \LogicException when the specified key does not exist
     * @return Promise<void>
     */
    public function unset(string $key, ChatRoom $room = null): Promise
    {
        return $this->accessor->writeCallback(function($data) use($key) {
            if (!array_key_exists($key, $data[$this->partitionName] ?? [])) {
                throw new \LogicException("Undefined key '{$key}'");
            }

            unset($data[$this->partitionName][$key]);
            return $data;
        }, $this->dataFileTemplate, $room);
    }

    /**
     * Determine whether a key exists in the data store
     *
     * @param ChatRoom|null $room
     * @return Promise<bool>
     */
    public function clear(ChatRoom $room = null): Promise
    {
        return $this->accessor->writeCallback(function($data) {
            unset($data[$this->partitionName]);
            return $data;
        }, $this->dataFileTemplate, $room);
    }

    /**
     * Get the value from the data store for the specified key
     *
     * @param ChatRoom|null $room
     * @return Promise<mixed>
     * @throws \LogicException when the specified key does not exist
     */
    public function getAll(ChatRoom $room = null): Promise
    {
        return resolve(function() use($room) {
            $data = yield $this->accessor->read($this->dataFileTemplate, $room);
            return $data[$this->partitionName] ?? [];
        });
    }

    /**
     * Get the value from the data store for the specified key
     *
     * @param ChatRoom|null $room
     * @return Promise<mixed>
     * @throws \LogicException when the specified key does not exist
     */
    public function getKeys(ChatRoom $room = null): Promise
    {
        return resolve(function() use($room) {
            $data = yield $this->accessor->read($this->dataFileTemplate, $room);
            return array_keys($data[$this->partitionName] ?? []);
        });
    }
}
