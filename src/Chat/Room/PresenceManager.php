<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

use function Amp\all;
use Amp\Deferred;
use Amp\Promise;
use Amp\Success;
use Ds\Queue;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\Entities\User;
use Room11\Jeeves\Chat\Client\PostFlags;
use Room11\Jeeves\Chat\WebSocket\HandlerFactory as WebSocketHandlerFactory;
use Room11\Jeeves\Storage\Room as RoomStorage;
use function Amp\resolve;
use function Amp\once;
use function Amp\cancel;

class PresenceManager
{
    private $storage;
    private $chatClient;
    private $urlResolver;
    private $handlerFactory;
    private $connector;

    private $actionQueues = [];
    private $timerWatchers = [];

    public function __construct(
        RoomStorage $storage,
        ChatClient $chatClient,
        EndpointURLResolver $urlResolver,
        WebSocketHandlerFactory $handlerFactory,
        Connector $connector,
        Collection $rooms
    ) {
        $this->storage = $storage;
        $this->chatClient = $chatClient;
        $this->urlResolver = $urlResolver;
        $this->handlerFactory = $handlerFactory;
        $this->connector = $connector;
        $this->rooms = $rooms;
    }

    private function executeActionsFromQueue(Identifier $identifier)
    {
        $ident = $identifier->getIdentString();

        /** @var Queue $queue */
        $queue = $this->actionQueues[$ident]['queue'];
        $this->actionQueues[$ident]['running'] = true;

        while ($queue->count() > 0) {
            /** @var callable $callable */
            /** @var array $args */
            /** @var Deferred $deferred */
            list($callable, $args, $deferred) = $queue->pop();

            try {
                $deferred->succeed(yield from $callable($identifier, ...$args));
            } catch (\Throwable $e) {
                $deferred->fail($e);
            }
        }

        $this->actionQueues[$ident]['running'] = false;
    }

    private function enqueueAction(callable $callable, Identifier $identifier, ...$args): Promise
    {
        $ident = $identifier->getIdentString();

        if (!isset($this->actionQueues[$ident])) {
            $queue = new Queue;
            $this->actionQueues[$ident] = ['queue' => $queue, 'running' => false];
        } else {
            $queue = $this->actionQueues[$ident]['queue'];
        }

        $queue->push([$callable, $args, $deferred = new Deferred]);

        if (!$this->actionQueues[$ident]['running']) {
            resolve($this->executeActionsFromQueue($identifier));
        }

        return $deferred->promise();
    }

    private function getRequiredApproveVoteCount(Identifier $identifier)
    {
        return min((int)ceil(count(yield $this->chatClient->getRoomOwners($identifier)) / 2), 3);
    }

    private function unapprovedRoomFirstReminder(Identifier $identifier)
    {
        if (yield $this->storage->isApproved($identifier)) {
            return;
        }

        $requiredApprovals = yield from $this->getRequiredApproveVoteCount($identifier);
        $currentApprovals = count(yield $this->storage->getApproveVotes($identifier));

        $message = "Hi! This is just a friendly reminder that I will leave the room in 12 hours if I have not been"
                 . " approved by {$requiredApprovals} room owners. So far I have {$currentApprovals} votes.";
        $room = $this->rooms->get($identifier);

        yield $this->chatClient->postMessage($room, $message, PostFlags::FORCE);
    }

    private function unapprovedRoomSecondReminder(Identifier $identifier)
    {
        if (yield $this->storage->isApproved($identifier)) {
            return;
        }

        $requiredApprovals = yield from $this->getRequiredApproveVoteCount($identifier);
        $currentApprovals = count(yield $this->storage->getApproveVotes($identifier));

        $message = "Hi! This is just a friendly reminder that I will leave the room in 1 hour if I have not been"
                 . " approved by {$requiredApprovals} room owners. So far I have {$currentApprovals} votes.";
        $room = $this->rooms->get($identifier);

        yield $this->chatClient->postMessage($room, $message, PostFlags::FORCE);
    }

    private function unapprovedRoomLeave(Identifier $identifier)
    {
        if (yield $this->storage->isApproved($identifier)) {
            return;
        }

        $requiredApprovals = yield from $this->getRequiredApproveVoteCount($identifier);

        $message = "I have not been approved by {$requiredApprovals} room owners, so I am leaving the room. Bye!";
        $room = $this->rooms->get($identifier);

        yield $this->chatClient->postMessage($room, $message, PostFlags::FORCE);

        //todo: actually leave the room
    }

    private function scheduleActionsForUnapprovedRoom(Identifier $identifier)
    {
        $now = time();
        $inviteTime = yield $this->storage->getInviteTimestamp($identifier);
        $remind1Delay = ($now - ($inviteTime + (3600 * 12))) * 1000;
        $remind2Delay = ($now - ($inviteTime + (3600 * 23))) * 1000;
        $leaveDelay = ($now - ($inviteTime + (3600 * 24))) * 1000;

        if ($remind1Delay > 0) {
            $this->timerWatchers[$identifier->getIdentString()] = once(function() use($identifier) {
                $this->enqueueAction([$this, 'unapprovedRoomFirstReminder'], $identifier);
            }, $remind1Delay);
        }

        if ($remind2Delay > 0) {
            $this->timerWatchers[$identifier->getIdentString()] = once(function() use($identifier) {
                $this->enqueueAction([$this, 'unapprovedRoomSecondReminder'], $identifier);
            }, $remind2Delay);
        }

        if ($leaveDelay > 0) {
            $this->timerWatchers[$identifier->getIdentString()] = once(function() use($identifier) {
                $this->enqueueAction([$this, 'unapprovedRoomLeave'], $identifier);
            }, $leaveDelay);
        }
    }

    private function checkAndAddApproveVote(Identifier $identifier, int $userID)
    {
        if (yield $this->storage->isApproved($identifier)) {
            throw new AlreadyApprovedException(
                "Room {$identifier->getIdentString()} already approved"
            );
        }

        if (!yield $this->chatClient->isRoomOwner($identifier, $userID)) {
            throw new UserNotAcceptableException(
                "User #{$userID} is not a room owner of {$identifier->getIdentString()}"
            );
        }

        if (!yield $this->storage->containsApproveVote($identifier, $userID)) {
            throw new UserAlreadyVotedException(
                "User #{$userID} have already cast an approve vote for {$identifier->getIdentString()}"
            );
        }

        yield $this->storage->addApproveVote($identifier, $userID);

        $requiredApprovals = yield from $this->getRequiredApproveVoteCount($identifier);
        $currentApprovals = count(yield $this->storage->getApproveVotes($identifier));

        if ($currentApprovals < $requiredApprovals) {
            return false;
        }

        yield $this->storage->setApproved($identifier);

        foreach ($this->timerWatchers[$identifier->getIdentString()] ?? [] as $watcherId) {
            cancel($watcherId);
        }

        return true;
    }

    private function connectRoom(Identifier $identifier, bool $permanent): Promise
    {
        return $this->connector->connect($this->handlerFactory->build($identifier, $this, $permanent));
    }

    private function restoreTransientRoom(Identifier $identifier)
    {
        yield $this->connectRoom($identifier, false);

        if (!yield $this->storage->isApproved($identifier)) {
            yield from $this->scheduleActionsForUnapprovedRoom($identifier);
        }
    }

    private function checkAndAddRoom(Identifier $identifier, int $invitingUserID)
    {
        if (yield $this->storage->containsRoom($identifier)) {
            throw new RoomAlreadyExistsException("Already present in {$identifier->getIdentString()}");
        }

        /** @var Room $room */
        $room = yield $this->connectRoom($identifier, false);
        yield $this->storage->addRoom($identifier, time());
        yield from $this->scheduleActionsForUnapprovedRoom($identifier);

        try {
            yield from $this->checkAndAddApproveVote($identifier, $invitingUserID);
        }
        catch (AlreadyApprovedException $e) { /* this should never happen but just in case */ }
        catch (UserNotAcceptableException $e) { /* this can happen but we don't care */ }

        /** @var User $invitingUser */
        $botUser = $room->getSessionInfo()->getUser();
        $invitingUser = (yield $this->chatClient->getChatUsers($room, $invitingUserID))[0];
        $invitingUserProfileURL = $this->urlResolver->getEndpointURL($room, Endpoint::CHAT_USER, $invitingUser->getId());

        $messages = [
            "Hi! I'm {$botUser->getName()}. I'm a bot."
          . " I was invited here by [{$invitingUser->getName()}]({$invitingUserProfileURL}). I don't have much"
          . " documentation at the moment, but what there is can be found [here](https://github.com/Room-11/Jeeves).",
        ];

        if (!yield $this->storage->isApproved($identifier)) {
            $requiredApprovals = yield from $this->getRequiredApproveVoteCount($identifier);
            $currentApprovals = count(yield $this->storage->getApproveVotes($identifier));

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
    }

    private function checkIfRoomIsApproved(Identifier $identifier)
    {
        return yield $this->storage->isApproved($identifier);
    }

    public function addRoom(Identifier $identifier, int $invitingUserID): Promise
    {
        return $this->enqueueAction([$this, 'checkAndAddRoom'], $identifier, $invitingUserID);
    }

    private $permanentRooms = [];

    public function restoreRooms(array $permanentRoomIdentifiers): Promise
    {
        return resolve(function() use($permanentRoomIdentifiers) {
            /** @var Identifier $identifier */
            $promises = [];

            foreach ($permanentRoomIdentifiers as $identifier) {
                $promises[] = $this->connectRoom($identifier, true);
                $this->permanentRooms[$identifier->getIdentString()] = true;
            }

            $transientRoomIdentifiers = array_map(function($ident) {
                return Identifier::createFromIdentString($ident, true);
            }, yield $this->storage->getAllRooms());

            foreach ($transientRoomIdentifiers as $identifier) {
                $promises[] = resolve($this->restoreTransientRoom($identifier));
            }

            yield all($promises);
        });
    }

    public function addApproveVote(Identifier $identifier, int $userID): Promise
    {
        return $this->enqueueAction([$this, 'checkAndAddApproveVote'], $identifier, $userID);
    }

    public function isApproved(Identifier $identifier): Promise
    {
        return isset($this->permanentRooms[$identifier->getIdentString()])
            ? new Success(true)
            : $this->enqueueAction([$this, 'checkIfRoomIsApproved'], $identifier);
    }
}
