<?php

namespace Room11\Jeeves\Chat\Client;

use Amp\Deferred;

class Mutex
{
    /**
     * @var Deferred
     */
    private $last;

    public function withLock(callable $callback): \Generator
    {
        /** @var Lock $lock */
        $lock = yield from $this->getLock();

        try {
            $result = $callback();
            return $result instanceof \Generator ? yield from $result : $result;
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

        return new class($deferred)
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
                if ($this->deferred !== null) {
                    $this->deferred->succeed();
                }

                $this->released = true;
            }
        };
    }
}
