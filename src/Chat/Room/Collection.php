<?php  declare(strict_types=1);

namespace Room11\Jeeves\Chat\Room;

use const Room11\Jeeves\ROOM_IDENTIFIER_EXPR;

class Collection implements \Iterator, \ArrayAccess, \Countable
{
    /**
     * @var Room[][]
     */
    private $rooms = [];

    private $currentCount = 0;

    private function normalizeIdentifier($identifier)
    {
        if (is_object($identifier)) {
            if ($identifier instanceof Room) {
                $identifier = $identifier->getIdentifier();
            }

            if ($identifier instanceof Identifier) {
                return [$identifier->getHost(), $identifier->getId()];
            }
        } else if (is_string($identifier)) {
            if (!preg_match('/^' . ROOM_IDENTIFIER_EXPR . '$/i', $identifier, $match)) {
                throw new InvalidRoomIdentifierException('Invalid identifier string format');
            }

            return [$match[1], (int)$match[2]];
        }

        throw new InvalidRoomIdentifierException('Identifier must be a string or identifiable object');
    }

    /**
     * Collection constructor.
     * @param Room[] $rooms
     */
    public function __construct(array $rooms = [])
    {
        array_map([$this, 'add'], $rooms);
    }

    public function add(Room $room)
    {
        list($host, $id) = $this->normalizeIdentifier($room);

        $this->rooms[$host][$id] = $room;
        $this->currentCount++;
    }

    /**
     * @param Room|Identifier|string $identifier
     * @throws InvalidRoomException
     */
    public function remove($identifier)
    {
        list($host, $id) = $this->normalizeIdentifier($identifier);

        if (!isset($this->rooms[$host][$id])) {
            throw new InvalidRoomException("Unknown room identifier: {$host}#{$id}");
        }

        unset($this->rooms[$host][$id]);
        $this->currentCount--;
    }

    /**
     * @param Room|Identifier|string $identifier
     * @return null|Room
     * @throws InvalidRoomException
     */
    public function get($identifier)
    {
        list($host, $id) = $this->normalizeIdentifier($identifier);

        if (!isset($this->rooms[$host][$id])) {
            throw new InvalidRoomException("Unknown room identifier: {$host}#{$id}");
        }

        return $this->rooms[$host][$id] ?? null;
    }

    /**
     * @param Room|Identifier|string $identifier
     * @return bool
     */
    public function contains($identifier): bool
    {
        list($host, $id) = $this->normalizeIdentifier($identifier);

        return isset($this->rooms[$host][$id]);
    }

    /* Below this point are all array-object implementations that are just wrappers over the methods above. They
       should not modify the values in $this->rooms directly!   If you foreach over this object and call methods
       which modify the content of the collection during the loop, the results will be unpredictable because the
       Iterator implementation uses the internal array pointers. Stop writing shitty code anyway. */

    /**
     * @return Room|false
     */
    public function current()
    {
        if (null === $key = key($this->rooms)) {
            return false;
        }

        return current($this->rooms[$key]);
    }

    public function next()
    {
        if (null === $key = key($this->rooms)) {
            return;
        }

        if (next($this->rooms[$key]) !== false) {
            return;
        }

        next($this->rooms);
        if (null === $key = key($this->rooms)) {
            return;
        }

        reset($this->rooms[$key]);
    }

    /**
     * @return Identifier|null
     */
    public function key()
    {
        if (false === $room = $this->current()) {
            return null;
        }

        return $room->getIdentifier();
    }

    public function valid()
    {
        return $this->current() !== false;
    }

    public function rewind()
    {
        reset($this->rooms);

        if (null !== $key = key($this->rooms)) {
            reset($this->rooms[$key]);
        }
    }

    public function offsetExists($identifier)
    {
        return $this->contains($identifier);
    }

    public function offsetGet($identifier)
    {
        return $this->get($identifier);
    }

    public function offsetSet($identifier, $room)
    {
        if (!$room instanceof Room) {
            throw new InvalidRoomException("Rooms must be instances of " . Room::class);
        }

        list($host, $id) = $this->normalizeIdentifier($identifier);
        if ($host !== $room->getIdentifier()->getHost() || $id !== $room->getIdentifier()->getId()) {
            throw new InvalidRoomException("Identifying key must match room identifier");
        }

        $this->add($room);
    }

    public function offsetUnset($identifier)
    {
        $this->remove($identifier);
    }

    public function count()
    {
        return $this->currentCount;
    }
}
