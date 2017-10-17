<?php declare(strict_types = 1);

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\StackChat\Room\Room as ChatRoom;

interface Room
{
    const MAX_LEAVE_VOTE_AGE = 3600; // -1 day

    function containsRoom(ChatRoom $room): Promise;

    function addRoom(ChatRoom $room, int $inviteTimestamp): Promise;

    function removeRoom(ChatRoom $room): Promise;

    function getAllRooms(): Promise;

    function getInviteTimestamp(ChatRoom $room): Promise;

    function containsApproveVote(ChatRoom $room, int $userId): Promise;

    function addApproveVote(ChatRoom $room, int $userId): Promise;

    function getApproveVotes(ChatRoom $room): Promise;

    function containsLeaveVote(ChatRoom $room, int $userId): Promise;

    function addLeaveVote(ChatRoom $room, int $userId): Promise;

    function getLeaveVotes(ChatRoom $room): Promise;

    function setApproved(ChatRoom $room, bool $approved): Promise;

    function isApproved(ChatRoom $room): Promise;
}
