<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat;

use Amp\Deferred;
use Amp\Pause;
use Amp\Promise;
use Ds\Queue;
use Psr\Log\LoggerInterface as Logger;
use Room11\Jeeves\Storage\Room as RoomStorage;
use Room11\StackChat\Auth\SessionTracker;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Client\PostFlags;
use Room11\StackChat\Endpoint;
use Room11\StackChat\EndpointURLResolver;
use Room11\StackChat\Entities\ChatUser;
use Room11\StackChat\Room\AclDataAccessor;
use Room11\StackChat\Room\Connector;
use Room11\StackChat\Room\Room;
use function Amp\all;
use function Amp\cancel;
use function Amp\once;
use function Amp\resolve;

class PresenceManager
{
    private const MAX_RECONNECT_ATTEMPTS = 1500; // a little over 1 day, in practice
    private const UNAPPROVED_REMINDER_1_DELAY_SECS = 60 * 60 * 12;
    private const UNAPPROVED_REMINDER_2_DELAY_SECS = 60 * 60 * 23;
    private const UNAPPROVED_LEAVE_DELAY_SECS = 60 * 60 * 24;

    private $storage;
    private $statusManager;
    private $chatClient;
    private $aclDataAccessor;
    private $urlResolver;
    private $eventDispatcherFactory;
    private $connector;
    private $sessions;
    private $logger;

    private $actionQueues = [];
    private $timerWatchers = [];

    public function __construct(
        RoomStorage $storage,
        RoomStatusManager $statusManager,
        ChatClient $chatClient,
        AclDataAccessor $aclDataAccessor,
        EndpointURLResolver $urlResolver,
        WebSocketEventDispatcherFactory $eventDispatcherFactory,
        Connector $connector,
        SessionTracker $sessions,
        Logger $logger
    ) {
        $this->storage = $storage;
        $this->statusManager = $statusManager;
        $this->chatClient = $chatClient;
        $this->aclDataAccessor = $aclDataAccessor;
        $this->urlResolver = $urlResolver;
        $this->eventDispatcherFactory = $eventDispatcherFactory;
        $this->connector = $connector;
        $this->sessions = $sessions;
        $this->logger = $logger;
    }

    private function executeActionsFromQueue(Room $room)
    {
        $ident = $room->getIdentString();

        /** @var Queue $queue */
        $queue = $this->actionQueues[$ident]['queue'];
        $this->actionQueues[$ident]['running'] = true;

        while ($queue->count() > 0) {
            /** @var callable $callable */
            /** @var array $args */
            /** @var Deferred $deferred */
            list($callable, $args, $deferred) = $queue->pop();

            try {
                $deferred->succeed(yield from $callable($room, ...$args));
            } catch (\Throwable $e) {
                $deferred->fail($e);
            }
        }

        $this->actionQueues[$ident]['running'] = false;
    }

    private function enqueueAction(callable $callable, Room $room, ...$args): Promise
    {
        $ident = $room->getIdentString();

        if (!isset($this->actionQueues[$ident])) {
            $queue = new Queue;
            $this->actionQueues[$ident] = ['queue' => $queue, 'running' => false];
        } else {
            $queue = $this->actionQueues[$ident]['queue'];
        }

        $queue->push([$callable, $args, $deferred = new Deferred]);

        if (!$this->actionQueues[$ident]['running']) {
            resolve($this->executeActionsFromQueue($room))->when(function(?\Throwable $error) {
                if ($error !== null) {
                    $this->logger->error("Unhandled exception while executing PresenceManager actions: {$error}");
                }
            });
        }

        return $deferred->promise();
    }

    private function unapprovedRoomFirstReminder(Room $room)
    {
        if (yield $this->storage->isApproved($room)) {
            return;
        }

        $requiredApprovals = yield from $this->getRequiredApproveVoteCount($room);
        $currentApprovals = count(yield $this->storage->getApproveVotes($room));

        $message = "Hi! This is just a friendly reminder that I will leave the room in 12 hours if I have not been"
                 . " approved by {$requiredApprovals} room owners. So far I have {$currentApprovals} votes.";

        yield $this->chatClient->postMessage($room, $message, PostFlags::FORCE);
    }

    private function unapprovedRoomSecondReminder(Room $room)
    {
        if (yield $this->storage->isApproved($room)) {
            return;
        }

        $requiredApprovals = yield from $this->getRequiredApproveVoteCount($room);
        $currentApprovals = count(yield $this->storage->getApproveVotes($room));

        $message = "Hi! This is just a friendly reminder that I will leave the room in 1 hour if I have not been"
                 . " approved by {$requiredApprovals} room owners. So far I have {$currentApprovals} votes.";

        yield $this->chatClient->postMessage($room, $message, PostFlags::FORCE);
    }

    private function unapprovedRoomLeave(Room $room)
    {
        if (yield $this->storage->isApproved($room)) {
            return;
        }

        $requiredApprovals = yield from $this->getRequiredApproveVoteCount($room);

        $message = "I have not been approved by {$requiredApprovals} room owners, so I am leaving the room. Bye!";

        yield $this->chatClient->postMessage($room, $message, PostFlags::FORCE);
        yield $this->leaveRoom($room);
    }

    private function scheduleActionsForUnapprovedRoom(Room $room, int $inviteTimestamp)
    {
        $now = time();

        $leaveDelay = ($now - ($inviteTimestamp + self::UNAPPROVED_LEAVE_DELAY_SECS)) * 1000;

        if ($leaveDelay < 0) {
            $this->enqueueAction([$this, 'unapprovedRoomLeave'], $room);
            return;
        }

        $ident = $room->getIdentString();

        $this->timerWatchers[$ident]['leave'] = once(function() use($room) {
            unset($this->timerWatchers[$room->getIdentString()]['leave']);
            $this->enqueueAction([$this, 'unapprovedRoomLeave'], $room);
        }, $leaveDelay);

        $remind2Delay = ($now - ($inviteTimestamp + self::UNAPPROVED_REMINDER_2_DELAY_SECS)) * 1000;
        if ($remind2Delay < 0) {
            return;
        }

        $this->timerWatchers[$ident]['remind2'] = once(function() use($room) {
            unset($this->timerWatchers[$room->getIdentString()]['remind2']);
            $this->enqueueAction([$this, 'unapprovedRoomSecondReminder'], $room);
        }, $remind2Delay);

        $remind1Delay = ($now - ($inviteTimestamp + self::UNAPPROVED_REMINDER_1_DELAY_SECS)) * 1000;
        if ($remind1Delay < 0) {
            return;
        }

        $this->timerWatchers[$ident]['remind1'] = once(function() use($room) {
            unset($this->timerWatchers[$room->getIdentString()]['remind1']);
            $this->enqueueAction([$this, 'unapprovedRoomFirstReminder'], $room);
        }, $remind1Delay);
    }

    private function removeScheduledActionsForUnapprovedRoom(Room $room)
    {
        foreach ($this->timerWatchers[$room->getIdentString()] ?? [] as $watcherId) {
            cancel($watcherId);
        }

        unset($this->timerWatchers[$room->getIdentString()]);
    }

    private function connectRoom(Room $room)
    {
        return $this->connector->connect($room, $this->eventDispatcherFactory->create($this));
    }

    private function reconnectRoom(Room $room)
    {
        $attempt = 1;

        do {
            try {
                $this->logger->debug("Reconnect to {$room}: attempt {$attempt}");
                return $this->connectRoom($room);
            } catch (\Exception $e) { // *not* Throwable on purpose! If we get one of those we should probably just bail.
                $retryIn = min($attempt * 5, 60);
                $this->logger->debug(
                    "Connection to {$room} failed! Retrying in {$retryIn} seconds."
                    . " The error was: " . trim($e->getMessage())
                );
                yield new Pause($retryIn * 1000);
            }
        } while ($attempt++ < self::MAX_RECONNECT_ATTEMPTS);

        return null;
    }

    private function restoreTransientRoom(Room $room)
    {
        $isApproved = yield $this->storage->isApproved($room);
        $inviteTimestamp = yield $this->storage->getInviteTimestamp($room);

        if (!$isApproved && time() > $inviteTimestamp + self::UNAPPROVED_LEAVE_DELAY_SECS) {
            $this->storage->removeRoom($room);
            return;
        }

        yield $this->connectRoom($room);

        if (!$isApproved) {
            yield from $this->scheduleActionsForUnapprovedRoom($room, $inviteTimestamp);
        }
    }

    private function leaveRoom(Room $room): Promise
    {
        assert(
            !$this->statusManager->isPermanent($room),
            new InvalidRoomException('Cannot leave a permanent room')
        );

        $this->storage->setApproved($room, false);
        $this->removeScheduledActionsForUnapprovedRoom($room);

        return all([
            $this->chatClient->leaveRoom($room),
            $this->storage->removeRoom($room)
        ]);
    }

    private function checkAndAddApproveVote(Room $room, int $userID)
    {
        if (yield $this->storage->isApproved($room)) {
            throw new AlreadyApprovedException(
                "Room {$room->getIdentString()} already approved"
            );
        }

        if (!yield $this->aclDataAccessor->isRoomOwner($room, $userID)) {
            throw new UserNotAcceptableException(
                "User #{$userID} is not a room owner of {$room->getIdentString()}"
            );
        }

        if (yield $this->storage->containsApproveVote($room, $userID)) {
            throw new UserAlreadyVotedException(
                "User #{$userID} has already cast an approve vote for {$room->getIdentString()}"
            );
        }

        yield $this->storage->addApproveVote($room, $userID);

        $requiredVotes = yield $this->getRequiredApproveVoteCount($room);
        $currentVotes = count(yield $this->storage->getApproveVotes($room));

        if ($currentVotes < $requiredVotes) {
            return [false, $currentVotes];
        }

        $this->removeScheduledActionsForUnapprovedRoom($room);
        yield $this->storage->setApproved($room, true);

        return [true, $currentVotes];
    }

    private function checkAndAddLeaveVote(Room $room, int $userID)
    {
        if (!yield $this->aclDataAccessor->isRoomOwner($room, $userID)) {
            throw new UserNotAcceptableException(
                "User #{$userID} is not a room owner of {$room->getIdentString()}"
            );
        }

        if (yield $this->storage->containsLeaveVote($room, $userID)) {
            throw new UserAlreadyVotedException(
                "User #{$userID} has already cast a leave vote for {$room->getIdentString()}"
            );
        }

        yield $this->storage->addLeaveVote($room, $userID);

        $requiredVotes = yield $this->getRequiredLeaveVoteCount($room);
        $currentVotes = count(yield $this->storage->getLeaveVotes($room));

        if ($currentVotes < $requiredVotes) {
            return [false, $currentVotes];
        }

        yield $this->chatClient->postMessage($room, "I've outstayed my welcome so I'm leaving. Bye!", PostFlags::FORCE);
        yield $this->leaveRoom($room);

        return [true, $currentVotes];
    }

    private function checkAndAddRoom(Room $room, int $invitingUserID)
    {
        if ($this->statusManager->isPermanent($room) || yield $this->storage->containsRoom($room)) {
            throw new RoomAlreadyExistsException("Already present in {$room->getIdentString()}");
        }

        /** @var Room $room */
        yield $this->connectRoom($room);

        $inviteTimestamp = time();

        yield $this->storage->addRoom($room, $inviteTimestamp);
        yield from $this->scheduleActionsForUnapprovedRoom($room, $inviteTimestamp);

        $isApproved = false;
        $currentVotes = 0;
        $requiredVotes = yield $this->getRequiredApproveVoteCount($room);

        try {
            list($isApproved, $currentVotes) = yield from $this->checkAndAddApproveVote($room, $invitingUserID);
        }
        catch (AlreadyApprovedException $e) { /* this should never happen but just in case */ }
        catch (UserNotAcceptableException $e) { /* this can happen but we don't care */ }

        /** @var ChatUser $invitingUser */
        $botUser = $this->sessions->getSessionForRoom($room)->getUser();
        $invitingUser = (yield $this->chatClient->getChatUsers($room, $invitingUserID))[0];
        $invitingUserProfileURL = $this->urlResolver->getEndpointURL($room, Endpoint::CHAT_USER, $invitingUser->getId());

        $messages = [
            "Hi! I'm {$botUser->getName()}. I'm a bot."
          . " I was invited here by [{$invitingUser->getName()}]({$invitingUserProfileURL}). I don't have much"
          . " documentation at the moment, but what there is can be found [here](https://github.com/Room-11/Jeeves).",
        ];

        if (!$isApproved) {
            $messages[] = "You can't use me in this room yet because not enough room owners have approved my presence here."
                        . " I need approval from {$requiredVotes} room owners, so far I have {$currentVotes} vote"
                        . ($currentVotes === 1 ? '' : 's') . ".";
            $messages[] = "To cast an approve vote, a room owner needs to invoke the 'approve' command by posting a message"
                        . " starting with `!!approve` - each room owner can only vote once. If I don't get approval within"
                        . " 24 hours, I will leave the room. I will remind you in 12 hours, and again 1 hour before.";
        }

        $leaveMessage = "To tell me to leave the room, room owners can invoke the `!!leave` command.";
        if (count(yield $this->aclDataAccessor->getRoomOwners($room)) > 1) {
            $leaveMessage .= " If two room owners do this within an hour, I will leave the room.";
        }

        $messages[] = $leaveMessage;

        foreach ($messages as $message) {
            yield $this->chatClient->postMessage($room, $message, PostFlags::FORCE);
        }
    }

    private function processDisconnectAndReconnectIfNecessary(Room $room)
    {
        if ($this->statusManager->isPermanent($room) || yield $this->storage->containsRoom($room)) {
            yield from $this->reconnectRoom($room);
        }
    }

    public function getRequiredApproveVoteCount(Room $room): Promise
    {
        return resolve(function() use($room) {
            return min((int)ceil(count(yield $this->aclDataAccessor->getRoomOwners($room)) / 2), 3);
        });
    }

    public function getRequiredLeaveVoteCount(Room $room): Promise
    {
        return resolve(function() use($room) {
            return min(count(yield $this->aclDataAccessor->getRoomOwners($room)), 2);
        });
    }

    public function addRoom(Room $room, int $invitingUserID): Promise
    {
        return $this->enqueueAction([$this, 'checkAndAddRoom'], $room, $invitingUserID);
    }

    public function restoreRooms(array $permanentRoomRooms): Promise
    {
        return resolve(function() use($permanentRoomRooms) {
            /** @var Room $room */
            $promises = [];

            foreach ($permanentRoomRooms as $room) {
                $promises[] = $this->connectRoom($room);
            }

            $transientRoomRooms = array_map(function($ident) {
                return Room::createFromIdentString($ident);
            }, yield $this->storage->getAllRooms());

            foreach ($transientRoomRooms as $room) {
                $promises[] = resolve($this->restoreTransientRoom($room));
            }

            yield all($promises);
        });
    }

    public function addApproveVote(Room $room, int $userID): Promise
    {
        return $this->enqueueAction([$this, 'checkAndAddApproveVote'], $room, $userID);
    }

    public function addLeaveVote(Room $room, int $userID): Promise
    {
        return $this->enqueueAction([$this, 'checkAndAddLeaveVote'], $room, $userID);
    }

    public function processDisconnect(Room $room): Promise
    {
        return $this->enqueueAction([$this, 'processDisconnectAndReconnectIfNecessary'], $room);
    }
}
