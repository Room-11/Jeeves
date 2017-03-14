<?php declare(strict_types = 1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;

interface Room
{
    const MAX_LEAVE_VOTE_AGE = 3600; // -1 day

    function containsRoom(ChatRoomIdentifier $identifier): Promise;

    function addRoom(ChatRoomIdentifier $identifier, int $inviteTimestamp): Promise;

    function removeRoom(ChatRoomIdentifier $identifier): Promise;

    function getAllRooms(): Promise;

    function getInviteTimestamp(ChatRoomIdentifier $identifier): Promise;

    function containsApproveVote(ChatRoomIdentifier $identifier, int $userId): Promise;

    function addApproveVote(ChatRoomIdentifier $identifier, int $userId): Promise;

    function getApproveVotes(ChatRoomIdentifier $identifier): Promise;

    function containsLeaveVote(ChatRoomIdentifier $identifier, int $userId): Promise;

    function addLeaveVote(ChatRoomIdentifier $identifier, int $userId): Promise;

    function getLeaveVotes(ChatRoomIdentifier $identifier): Promise;

    function setApproved(ChatRoomIdentifier $identifier, bool $approved): Promise;

    function isApproved(ChatRoomIdentifier $identifier): Promise;
}
