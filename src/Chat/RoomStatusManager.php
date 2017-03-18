<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Storage\Room as RoomStorage;
use Room11\StackChat\Room\Identifier;
use Room11\StackChat\Room\PostPermissionManager;

class RoomStatusManager implements PostPermissionManager
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


    public function isPostAllowed(Identifier $identifier): Promise
    {
        return $this->isApproved($identifier);
    }
}
