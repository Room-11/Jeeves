<?php declare(strict_types = 1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;

interface Room
{
    public function addRoom(ChatRoomIdentifier $room, int $inviteTimestamp): Promise;

    public function removeRoom(ChatRoomIdentifier $room): Promise;

    public function getAllRooms(ChatRoomIdentifier $room): Promise;

    public function getInviteTimestamp(ChatRoomIdentifier $room): Promise;

    public function addApproveVote(ChatRoomIdentifier $room, int $userId): Promise;

    public function getApproveVotes(ChatRoomIdentifier $room): Promise;

    public function addLeaveVote(ChatRoomIdentifier $room, int $userId): Promise;

    public function getLeaveVotes(ChatRoomIdentifier $room): Promise;

    public function isApproved(ChatRoomIdentifier $room): Promise;
}
