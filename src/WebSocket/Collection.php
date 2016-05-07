<?php

namespace Room11\Jeeves\WebSocket;

use Amp\Promise;

class Collection
{
    private $promises = [];

    public function add(int $id, Promise $promise)
    {
        $this->promises[$id] = $promise;
    }

    public function remove(int $id)
    {
        if (!isset($this->promises[$id])) {
            throw new \LogicException('ID ' . $id . ' not present in collection');
        }

        unset($this->promises[$id]);
    }

    /**
     * @param Promise|int $idOrPromise
     * @return bool
     */
    public function contains($idOrPromise): bool
    {
        if ($idOrPromise instanceof Promise) {
            return in_array($idOrPromise, $this->promises, true);
        }

        return isset($this->promises[$idOrPromise]);
    }

    public function yieldAll(): \Generator
    {
        while ($this->promises) {
            yield reset($this->promises);
        }
    }
}
