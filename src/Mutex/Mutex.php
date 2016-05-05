<?php

namespace Room11\Jeeves\Mutex;

interface Mutex
{
    public function withLock(callable $callback): \Generator;
    public function getLock(): \Generator;
}
