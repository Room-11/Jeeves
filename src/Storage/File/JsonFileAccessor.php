<?php declare(strict_types = 1);

namespace Room11\Jeeves\Storage\File;

use Amp\Deferred;
use Amp\Mutex\QueuedExclusiveMutex;
use Amp\Promise;
use function Amp\File\exists;
use function Amp\File\get;
use function Amp\File\put;
use function Amp\resolve;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class JsonFileAccessor
{
    /**
     * @var array[]
     */
    private $dataCache = [];

    /**
     * @var Promise[]
     */
    private $loadPromises = [];

    /**
     * @var QueuedExclusiveMutex[]
     */
    private $lockMutexes = [];

    private function getDataFileName($room, $template): string
    {
        $ident = $room instanceof ChatRoom ? $room->getIdentifier()->getIdentString() : 'global';
        return sprintf($template, $ident);
    }

    private function loadFile(string $filePath): \Generator
    {
        if (isset($this->loadPromises[$filePath])) {
            yield $this->loadPromises[$filePath];
            return;
        }

        $deferred = new Deferred();
        $this->loadPromises[$filePath] = $deferred->promise();

        if (!isset($this->lockMutexes[$filePath])) {
            $this->lockMutexes[$filePath] = new QueuedExclusiveMutex();
        }

        yield $this->lockMutexes[$filePath]->withLock(function() use($filePath, $deferred) {
            try {
                // we may have been waiting on a lock and it's been populated by now
                if (isset($this->dataCache[$filePath])) {
                    return;
                }

                $this->dataCache[$filePath] = (yield exists($filePath))
                    ? json_try_decode(yield get($filePath), true)
                    : [];
            } catch (\Throwable $e) {
                $this->dataCache[$filePath] = [];
            } finally {
                $deferred->succeed();
                unset($this->loadPromises[$filePath]);
            }
        });
    }

    private function saveFile(string $filePath, callable $callback): \Generator
    {
        if (!isset($this->lockMutexes[$filePath])) {
            $this->lockMutexes[$filePath] = new QueuedExclusiveMutex();
        }

        yield $this->lockMutexes[$filePath]->withLock(function() use($filePath, $callback) {
            $data = $callback($this->dataCache[$filePath] ?? []);

            // make sure we can persist it before updating the store
            yield put($filePath, json_try_encode($data));

            $this->dataCache[$filePath] = $data;
        });
    }

    /**
     * @param string $filePathTemplate
     * @param ChatRoom|null $room
     * @return Promise
     */
    public function read(string $filePathTemplate, ChatRoom $room = null): Promise
    {
        $filePath = $this->getDataFileName($room, $filePathTemplate);

        return resolve(function() use($filePath) {
            if (!isset($this->dataCache[$filePath])) {
                yield from $this->loadFile($filePath);
            }

            return $this->dataCache[$filePath];
        });
    }

    /**
     * @param array $data
     * @param string $filePathTemplate
     * @param ChatRoom|null $room
     * @return Promise
     */
    public function write(array $data, string $filePathTemplate, ChatRoom $room = null): Promise
    {
        $filePath = $this->getDataFileName($room, $filePathTemplate);

        return resolve($this->saveFile($filePath, function() use($data) {
            return $data;
        }));
    }

    /**
     * @param callable $callback
     * @param string $filePathTemplate
     * @param ChatRoom|null $room
     * @return Promise
     */
    public function writeCallback(callable $callback, string $filePathTemplate, ChatRoom $room = null): Promise
    {
        $filePath = $this->getDataFileName($room, $filePathTemplate);

        return resolve($this->saveFile($filePath, $callback));
    }
}
