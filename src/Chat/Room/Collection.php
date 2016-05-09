<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\Room;

class Collection
{
    private $rooms = [];

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
        $identifier = $room->getIdentifier();
        $this->rooms[$identifier->getHost()][$identifier->getId()] = $room;
    }

    public function remove(Room $room)
    {
        $identifier = $room->getIdentifier();
        unset($this->rooms[$identifier->getHost()][$identifier->getId()]);
    }

    /**
     * @param string $host
     * @param int $id
     * @return Room|null
     */
    public function get(string $host, int $id)
    {
        return $this->rooms[$host][$id] ?? null;
    }

    /**
     * @param Identifier $identifier
     * @return Room|null
     */
    public function getByIdentifier(Identifier $identifier)
    {
        return $this->rooms[$identifier->getHost()][$identifier->getId()] ?? null;
    }
}
