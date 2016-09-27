<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\Entities\User;
use Room11\Jeeves\Chat\Client\PostFlags;
use Room11\Jeeves\Storage\Room as RoomStorage;
use function Amp\resolve;

class PresenceManager
{
    private $storage;
    private $chatClient;
    private $urlResolver;

    public function __construct(RoomStorage $storage, ChatClient $chatClient, EndpointURLResolver $urlResolver)
    {
        $this->storage = $storage;
        $this->chatClient = $chatClient;
        $this->urlResolver = $urlResolver;
    }

    private function getRequiredApproveVoteCount(Room $room): \Generator
    {
        return min((int)ceil(count(yield $this->chatClient->getRoomOwners($room)) / 2), 3);
    }

    public function addRoom(Room $room, int $invitingUserID): Promise
    {
        return resolve(function() use($room, $invitingUserID) {
            yield $this->storage->addRoom($room->getIdentifier(), time());

            try {
                yield $this->addApproveVote($room, $invitingUserID);
            } catch (UserNotAcceptableException $e) {}

            /** @var User $invitingUser */
            $botUser = $room->getSessionInfo()->getUser();
            $invitingUser = (yield $this->chatClient->getChatUsers($room, $invitingUserID))[0];
            $invitingUserProfileURL = $this->urlResolver->getEndpointURL($room, Endpoint::CHAT_USER, $invitingUser->getId());

            $messages = [
                "Hi! I'm {$botUser->getName()}. I'm a bot."
              . " I was invited here by [{$invitingUser->getName()}]({$invitingUserProfileURL}). I don't have much"
              . " documentation at the moment, but what there is can be found [here](https://github.com/Room-11/Jeeves).",
            ];

            if (!yield $room->isApproved()) {
                $requiredApprovals = yield from $this->getRequiredApproveVoteCount($room);
                $currentApprovals = count(yield $this->storage->getApproveVotes($room->getIdentifier()));

                $messages[] = "You can't use me in this room yet because not enough room owners have approved my presence here."
                            . " I need approval from {$requiredApprovals} room owners, so far I have {$currentApprovals} vote"
                            . ($currentApprovals === 1 ? '' : 's') . ".";
                $messages[] = "To cast an approve vote, a room owner needs to invoke the 'approve' command by posting a message"
                            . " starting with `!!approve` - each room owner can only vote once. If I don't get approval within"
                            . " 24 hours, I will leave the room. I will remind you in 12 hours, and again 1 hour before.";
            }

            $leaveMessage = "To tell me to leave the room, room owners can invoke the `!!leave` command.";
            if (count(yield $this->chatClient->getRoomOwners($room)) > 1) {
                $leaveMessage .= " If two room owners do this within an hour, I will leave the room.";
            }

            $messages[] = $leaveMessage;

            foreach ($messages as $message) {
                yield $this->chatClient->postMessage($room, $message, PostFlags::FORCE);
            }
        });
    }

    public function restoreRoom()
    {

    }

    public function addApproveVote(Room $room, int $userID): Promise
    {
        return resolve(function() use($room, $userID) {
            assert(
                !yield $room->isApproved(),
                new \LogicException('Room ' . $room->getIdentifier()->getIdentString() . ' already approved')
            );

            if (!yield $this->chatClient->isRoomOwner($room, $userID)) {
                throw new UserNotAcceptableException("User #{$userID} is not a room owner of {$room->getIdentifier()->getIdentString()}");
            }

            yield $this->storage->addApproveVote($room->getIdentifier(), $userID);

            $requiredApprovals = yield from $this->getRequiredApproveVoteCount($room);
            $currentApprovals = count(yield $this->storage->getApproveVotes($room->getIdentifier()));

            if ($currentApprovals >= $requiredApprovals) {
                yield $this->storage->setApproved($room->getIdentifier());
                return true;
            }

            return false;
        });
    }
}
