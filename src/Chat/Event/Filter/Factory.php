<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event\Filter;

class Factory
{
    public function build(string $filter, array $predicates, array $rooms, array $types, callable $callback)
    {
        return new Filter($filter, $predicates, $types, $rooms, $callback);
    }
}
