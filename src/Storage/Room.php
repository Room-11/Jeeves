<?php declare(strict_types = 1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

interface Room
{
    public function addWelcomeVote(ChatRoomIdentifier $room, int $userId): Promise;

    public function getWelcomeVotes(ChatRoomIdentifier $room): Promise;

    public function addLeaveVote(ChatRoomIdentifier $room, int $userId): Promise;

    public function getLeaveVotes(ChatRoomIdentifier $room): Promise;

    public function clear(ChatRoomIdentifier $room);
}
