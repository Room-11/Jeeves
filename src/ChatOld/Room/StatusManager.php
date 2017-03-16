<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Storage\Room as RoomStorage;

class StatusManager
{
    private $roomStorage;
    private $permanentRooms = [];

    /**
     * @param RoomStorage $roomStorage
     * @param Identifier[] $permanentRooms
     */
    public function __construct(RoomStorage $roomStorage, array $permanentRooms)
    {
        $this->roomStorage = $roomStorage;

        foreach ($permanentRooms as $identifier) {
            $this->permanentRooms[$identifier->getIdentString()] = true;
        }
    }

    public function isPermanent(Identifier $identifier): bool
    {
        return !empty($this->permanentRooms[$identifier->getIdentString()]);
    }

    public function isApproved(Identifier $identifier): Promise
    {
        return empty($this->permanentRooms[$identifier->getIdentString()])
            ? $this->roomStorage->isApproved($identifier)
            : new Success(true);
    }
}
