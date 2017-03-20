<?php declare(strict_types = 1);

namespace Room11\Jeeves\Storage\File;

use Amp\Promise;
use Room11\Jeeves\Storage\KeyValue as KeyValueStorage;
use Room11\StackChat\Room\Room as ChatRoom;
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
     * {@inheritdoc}
     */
    public function getPartitionName(): string
    {
        return $this->partitionName;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key, ChatRoom $room = null): Promise
    {
        return resolve(function() use($key, $room) {
            $data = yield $this->accessor->read($this->dataFileTemplate, $room);
            return isset($data[$this->partitionName][$key]);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, ChatRoom $room = null): Promise
    {
        return resolve(function() use($key, $room) {
            $data = yield $this->accessor->read($this->dataFileTemplate, $room);
            return $data[$this->partitionName][$key] ?? null;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, ChatRoom $room = null): Promise
    {
        return $this->accessor->writeCallback(function($data) use($key, $value) {
            $data[$this->partitionName][$key] = $value;
            return $data;
        }, $this->dataFileTemplate, $room);
    }

    /**
     * {@inheritdoc}
     */
    public function unset(string $key, ChatRoom $room = null): Promise
    {
        return $this->accessor->writeCallback(function($data) use($key) {
            unset($data[$this->partitionName][$key]);
            return $data;
        }, $this->dataFileTemplate, $room);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(ChatRoom $room = null): Promise
    {
        return $this->accessor->writeCallback(function($data) {
            unset($data[$this->partitionName]);
            return $data;
        }, $this->dataFileTemplate, $room);
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(ChatRoom $room = null): Promise
    {
        return resolve(function() use($room) {
            $data = yield $this->accessor->read($this->dataFileTemplate, $room);
            return $data[$this->partitionName] ?? [];
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getKeys(ChatRoom $room = null): Promise
    {
        return resolve(function() use($room) {
            $data = yield $this->accessor->read($this->dataFileTemplate, $room);
            return array_keys($data[$this->partitionName] ?? []);
        });
    }
}
