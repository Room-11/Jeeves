<?php declare(strict_types = 1);

namespace Room11\Jeeves\Mutex;

use Amp\Deferred;
use Amp\Promise;

class QueuedExclusiveMutex implements Mutex
{
    /**
     * @var Deferred
     */
    private $last;

    public function withLock(callable $callback): \Generator
    {
        $lock = yield from $this->getLock();

        try {
            $result = $callback();

            if ($result instanceof \Generator) {
                return yield from $result;
            }

            if ($result instanceof Promise) {
                return yield $result;
            }

            return $result;
        } finally {
            $lock->release();
        }
    }

    public function getLock(): \Generator
    {
        $deferred = new Deferred();
        $last = $this->last;

        $this->last = $deferred;

        if ($last !== null) {
            yield $last->promise();
        }

        return new class($deferred) implements Lock
        {
            private $deferred;

            private $released;

            public function __construct(Deferred $deferred = null)
            {
                $this->deferred = $deferred;
            }

            public function __destruct()
            {
                if (!$this->released) {
                    $this->release();
                }
            }

            public function release()
            {
                $this->deferred->succeed();
                $this->released = true;
            }
        };
    }
}
