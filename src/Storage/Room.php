<?php declare(strict_types = 1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;

interface Room
{
    const MAX_LEAVE_VOTE_AGE = 3600; // -1 day

    public function containsRoom(ChatRoomIdentifier $identifier): Promise;

    public function addRoom(ChatRoomIdentifier $identifier, int $inviteTimestamp): Promise;

    public function removeRoom(ChatRoomIdentifier $identifier): Promise;

    public function getAllRooms(): Promise;

    public function getInviteTimestamp(ChatRoomIdentifier $identifier): Promise;

    public function containsApproveVote(ChatRoomIdentifier $identifier, int $userId): Promise;

    public function addApproveVote(ChatRoomIdentifier $identifier, int $userId): Promise;

    public function getApproveVotes(ChatRoomIdentifier $identifier): Promise;

    public function containsLeaveVote(ChatRoomIdentifier $identifier, int $userId): Promise;

    public function addLeaveVote(ChatRoomIdentifier $identifier, int $userId): Promise;

    public function getLeaveVotes(ChatRoomIdentifier $identifier): Promise;

    public function setApproved(ChatRoomIdentifier $identifier): Promise;

    public function isApproved(ChatRoomIdentifier $identifier): Promise;
}
