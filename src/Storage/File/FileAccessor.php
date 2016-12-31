<?php declare(strict_types = 1);

namespace Room11\Jeeves\Storage\File;

use Amp\Deferred;
use Amp\Mutex\QueuedExclusiveMutex;
use Amp\Promise;
use function Amp\File\exists;
use function Amp\File\get;
use function Amp\File\put;
use function Amp\resolve;

class FileAccessor
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

    private function loadFile(string $filePath): \Generator
    {
        if (isset($this->loadPromises[$filePath])) {
            yield $this->loadPromises[$filePath];
            return $this->dataCache[$filePath];
        }

        $deferred = new Deferred();
        $this->loadPromises[$filePath] = $deferred->promise();

        $this->lockMutexes[$filePath] = new QueuedExclusiveMutex();

        return yield $this->lockMutexes[$filePath]->withLock(function() use($filePath, $deferred) {
            try {
                // we may have been waiting on a lock and it's been populated by now
                if (!isset($this->dataCache[$filePath])) {
                    $this->dataCache[$filePath] = (yield exists($filePath))
                        ? yield get($filePath)
                        : "";
                }
            } catch (\Throwable $e) {
                $this->dataCache[$filePath] = [];
            } finally {
                $deferred->succeed();
                unset($this->loadPromises[$filePath]);
            }

            return $this->dataCache[$filePath];
        });
    }

    private function saveFile(string $filePath, callable $callback): \Generator
    {
        if (!isset($this->dataCache[$filePath])) {
            yield from $this->loadFile($filePath);
        }

        return yield $this->lockMutexes[$filePath]->withLock(function() use($filePath, $callback) {
            $data = $callback($this->dataCache[$filePath]);

            if (!is_string($data)) {
                throw new \LogicException('File Accessor can only deal with strings.');
            }

            // make sure we can persist it before updating the store
            yield put($filePath, $data);

            return $this->dataCache[$filePath] = $data;
        });
    }

    /**
     * @param string $filePath
     * @return Promise
     */
    public function read(string $filePath): Promise
    {
        return resolve(function() use($filePath) {
            if (!isset($this->dataCache[$filePath])) {
                yield from $this->loadFile($filePath);
            }

            return $this->dataCache[$filePath];
        });
    }

    /**
     * @param array $data
     * @param string $filePath
     * @return Promise
     */
    public function write(array $data, string $filePath): Promise
    {
        return resolve($this->saveFile($filePath, function() use($data) {
            return $data;
        }));
    }

    /**
     * @param callable $callback
     * @param string $filePath
     * @return Promise
     */
    public function writeCallback(callable $callback, string $filePath): Promise
    {
        return resolve($this->saveFile($filePath, $callback));
    }
}
