<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Storage\Room as RoomStorage;
use Room11\StackChat\Room\PostPermissionManager;
use Room11\StackChat\Room\Room;

class RoomStatusManager implements PostPermissionManager
{
    private $roomStorage;
    private $permanentRooms = [];

    /**
     * @param RoomStorage $roomStorage
     * @param Room[] $permanentRooms
     */
    public function __construct(RoomStorage $roomStorage, array $permanentRooms)
    {
        $this->roomStorage = $roomStorage;

        foreach ($permanentRooms as $identifier) {
            $this->permanentRooms[$identifier->getIdentString()] = true;
        }
    }

    public function isPermanent(Room $identifier): bool
    {
        return !empty($this->permanentRooms[$identifier->getIdentString()]);
    }

    public function isApproved(Room $identifier): Promise
    {
        return empty($this->permanentRooms[$identifier->getIdentString()])
            ? $this->roomStorage->isApproved($identifier)
            : new Success(true);
    }


    public function isPostAllowed(Room $identifier): Promise
    {
        return $this->isApproved($identifier);
    }
}
