<?php

namespace Room11\Jeeves\Mutex;

use Amp\Promise;

abstract class Mutex
{
    public function withLock(callable $callback): \Generator
    {
        /** @var Lock $lock */
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

    abstract public function getLock(): \Generator;
}
