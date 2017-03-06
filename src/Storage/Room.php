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

    public function setApproved(ChatRoomIdentifier $identifier, bool $approved): Promise;

    public function isApproved(ChatRoomIdentifier $identifier): Promise;

    /**
     * Check if Jeeves is muted in a given room.
     * @param ChatRoomIdentifier $identifier
     * @return Promise Resolves as true if the Jeeves is muted in the room. False if not.
     */
    public function isMuted(ChatRoomIdentifier $identifier): Promise;

    /**
     * Check if Jeeves is indefinitely muted in a given room.
     * @param ChatRoomIdentifier $identifier
     * @return Promise
     */
    public function isMutedForever(ChatRoomIdentifier $identifier): Promise;

    /**
     * Retrieve the duration that Jeeves will remain muted until.
     * Resolution is one of the following:
     *     * A UNIX timestamp which indicates the point in time Jeeves will be muted until.
     *     * Null which indicates either the room is not muted, or it is muted indefinitely.
     * Check these conditions prior to this by calling isMuted and isMutedForever
     * @param ChatRoomIdentifier $identifier
     * @return Promise
     */
    public function getMuteExpiration(ChatRoomIdentifier $identifier): Promise;

    /**
     * Mute Jeeves in a room until the given time.
     * @param ChatRoomIdentifier $identifier
     * @param int $expires A UNIX timestamp, indicates when the mute will expire.
     * @return Promise
     */
    public function mute(ChatRoomIdentifier $identifier, int $expires): Promise;

    /**
     * Indefinitely mute Jeeves in a room.
     * @param ChatRoomIdentifier $identifier
     * @return Promise
     */
    public function muteForever(ChatRoomIdentifier $identifier): Promise;

    /**
     * Un-mute Jeeves in a given room.
     * Should also resolve successfully if Jeeves was never muted.
     * @param ChatRoomIdentifier $identifier
     * @return Promise
     */
    public function unMute(ChatRoomIdentifier $identifier): Promise;
}
