<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event\Filter;

use Amp\Promise;
use Room11\Jeeves\Chat\Event\Event;
use function Amp\resolve;

class Filter
{
    private $filter;
    private $predicates;
    private $rooms;
    private $types;
    private $callback;

    /**
     * @param string $filter
     * @param callable[] $predicates
     * @param string[] $rooms
     * @param int[] $types
     * @param callable $callback
     */
    public function __construct(string $filter, array $predicates, array $rooms, array $types, callable $callback)
    {
        $this->filter = $filter;
        $this->predicates = $predicates;
        $this->rooms = $rooms;
        $this->types = $types;
        $this->callback = $callback;
    }

    /**
     * @param Event $event
     * @return Promise|null
     */
    public function executeForEvent(Event $event)
    {
        foreach ($this->predicates as $predicate) {
            if (!$predicate($event)) {
                return null;
            }
        }

        $handler = $this->callback;
        $result = $handler($event);

        if ($result instanceof \Generator) {
            return resolve($result);
        } else if ($handler instanceof Promise) {
            return $result;
        }

        return null;
    }

    public function getFilter(): string
    {
        return $this->filter;
    }

    /**
     * @return string[]
     */
    public function getRooms(): array
    {
        return $this->rooms;
    }

    /**
     * @return int[]
     */
    public function getTypes(): array
    {
        return $this->types;
    }
}
