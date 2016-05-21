<?php declare(strict_types = 1);

namespace Room11\Jeeves\Storage\File;

use Amp\Deferred;
use Amp\Mutex\QueuedExclusiveMutex;
use Amp\Promise;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\KeyValue as KeyValueStoreStorage;
use function Amp\File\exists;
use function Amp\File\get;
use function Amp\File\put;
use function Amp\resolve;

/**
 * I don't usually add class-level docblocks, but I am genuinely sorry for this
 */
class KeyValue implements KeyValueStoreStorage
{
    private static $dataFileTemplate;
    private $partitionName;

    private static $dataCache = [];

    public function __construct(string $dataFile, string $partitionName)
    {
        $this->partitionName = $partitionName;

        if (!isset(self::$dataFileTemplate)) {
            self::$dataFileTemplate = $dataFile;
        }
    }

    private static function getDataFileName($room): string
    {
        $ident = $room instanceof ChatRoom ? $room->getIdentifier()->getIdentString() : 'global';
        return sprintf(self::$dataFileTemplate, $ident);
    }

    private static function read($room, $partitionName): \Generator
    {
        $filePath = self::getDataFileName($room);

        if (isset(self::$dataCache[$filePath]['data'])) {
            return self::$dataCache[$filePath]['data'][$partitionName] ?? [];
        }

        if (isset(self::$dataCache[$filePath]['read_promise'])) {
            yield self::$dataCache[$filePath]['read_promise'];
            return self::$dataCache[$filePath]['data'][$partitionName] ?? [];
        }

        $deferred = new Deferred();
        self::$dataCache[$filePath] = ['read_promise' => $deferred->promise()];

        if (!yield exists($filePath)) {
            self::$dataCache[$filePath]['data'] = [];

            $deferred->succeed();
            unset(self::$dataCache[$filePath]['read_promise']);

            return [];
        }

        try {
            self::$dataCache[$filePath]['data'] = json_try_decode(yield get($filePath), true);
        } catch (\Throwable $e) {
            return [];
        } finally {
            $deferred->succeed();
            unset(self::$dataCache[$filePath]['read_promise']);
        }

        return self::$dataCache[$filePath]['data'][$partitionName] ?? [];
    }

    private static function write($room, $partitionName, array $data): \Generator
    {
        $filePath = self::getDataFileName($room);

        // make sure we can persist it before updating the store
        $tmp = self::$dataCache[$filePath]['data'];
        $tmp[$partitionName] = $data;
        $json = json_try_encode($tmp);
        self::$dataCache[$filePath]['data'] = $tmp;

        if (!isset(self::$dataCache[$filePath]['write_mutex'])) {
            $mutex = new QueuedExclusiveMutex();
            self::$dataCache[$filePath]['write_mutex'] = $mutex;
        } else {
            $mutex = self::$dataCache[$filePath]['write_mutex'];
        }

        yield $mutex->withLock(function() use($filePath, $json) {
            yield put($filePath, $json);
        });
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
            return array_key_exists($key, yield from self::read($room, $this->partitionName));
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
            $data = yield from self::read($room, $this->partitionName);

            if (!array_key_exists($key, $data)) {
                throw new \LogicException("Undefined key '{$key}'");
            }

            return $data[$key];
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
        return resolve(function() use($key, $value, $room) {
            $data = yield from self::read($room, $this->partitionName);
            $data[$key] = $value;
            yield from self::write($room, $this->partitionName, $data);
        });
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
        return resolve(function() use($key, $room) {
            $data = yield from self::read($room, $this->partitionName);

            if (!array_key_exists($key, $data)) {
                throw new \LogicException("Undefined key '{$key}'");
            }

            unset($data[$key]);
            yield from self::write($room, $this->partitionName, $data);
        });
    }
}
