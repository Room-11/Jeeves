<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Deferred;
use Amp\Pause;
use Amp\Promise;
use Amp\Success;
use Ds\Queue;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\PostFlags;
use Room11\Jeeves\Chat\Endpoint;
use Room11\Jeeves\Chat\EndpointURLResolver;
use Room11\Jeeves\Chat\Entities\ChatUser;
use Room11\Jeeves\Chat\WebSocket\EventDispatcher;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Storage\Room as RoomStorage;
use function Amp\all;
use function Amp\cancel;
use function Amp\once;
use function Amp\resolve;

class PresenceManager
{
    const MAX_RECONNECT_ATTEMPTS = 1500; // a little over 1 day, in practice

    private $storage;
    private $chatClient;
    private $httpClient;
    private $urlResolver;
    private $identifierFactory;
    private $connector;
    private $connectedRooms;
    private $eventDispatcher;
    private $logger;

    private $actionQueues = [];
    private $timerWatchers = [];
    private $permanentRooms = [];

    public function __construct(
        RoomStorage $storage,
        ChatClient $chatClient,
        HttpClient $httpClient,
        EndpointURLResolver $urlResolver,
        IdentifierFactory $identifierFactory,
        Connector $connector,
        ConnectedRoomCollection $connectedRooms,
        EventDispatcher $eventDispatcher,
        Logger $logger
    ) {
        $this->storage = $storage;
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->urlResolver = $urlResolver;
        $this->identifierFactory = $identifierFactory;
        $this->connector = $connector;
        $this->connectedRooms = $connectedRooms;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
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

    private function unapprovedRoomFirstReminder(Identifier $identifier)
    {
        if (yield $this->storage->isApproved($identifier)) {
            return;
        }

        $requiredApprovals = yield from $this->getRequiredApproveVoteCount($identifier);
        $currentApprovals = count(yield $this->storage->getApproveVotes($identifier));

        $message = "Hi! This is just a friendly reminder that I will leave the room in 12 hours if I have not been"
                 . " approved by {$requiredApprovals} room owners. So far I have {$currentApprovals} votes.";
        $room = $this->connectedRooms->get($identifier);

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
        $room = $this->connectedRooms->get($identifier);

        yield $this->chatClient->postMessage($room, $message, PostFlags::FORCE);
    }

    private function unapprovedRoomLeave(Identifier $identifier)
    {
        if (yield $this->storage->isApproved($identifier)) {
            return;
        }

        $requiredApprovals = yield from $this->getRequiredApproveVoteCount($identifier);

        $message = "I have not been approved by {$requiredApprovals} room owners, so I am leaving the room. Bye!";
        $room = $this->connectedRooms->get($identifier);

        yield $this->chatClient->postMessage($room, $message, PostFlags::FORCE);
        yield $this->leaveRoom($room);
    }

    private function scheduleActionsForUnapprovedRoom(Identifier $identifier)
    {
        $now = time();
        $inviteTime = yield $this->storage->getInviteTimestamp($identifier);
        $remind1Delay = ($now - ($inviteTime + (3600 * 12))) * 1000;
        $remind2Delay = ($now - ($inviteTime + (3600 * 23))) * 1000;
        $leaveDelay = ($now - ($inviteTime + (3600 * 24))) * 1000;

        if ($remind1Delay > 0) {
            $this->timerWatchers[$identifier->getIdentString()]['remind1'] = once(function() use($identifier) {
                unset($this->timerWatchers[$identifier->getIdentString()]['remind1']);
                $this->enqueueAction([$this, 'unapprovedRoomFirstReminder'], $identifier);
            }, $remind1Delay);
        }

        if ($remind2Delay > 0) {
            $this->timerWatchers[$identifier->getIdentString()]['remind2'] = once(function() use($identifier) {
                unset($this->timerWatchers[$identifier->getIdentString()]['remind2']);
                $this->enqueueAction([$this, 'unapprovedRoomSecondReminder'], $identifier);
            }, $remind2Delay);
        }

        if ($leaveDelay > 0) {
            $this->timerWatchers[$identifier->getIdentString()]['leave'] = once(function() use($identifier) {
                unset($this->timerWatchers[$identifier->getIdentString()]['leave']);
                $this->enqueueAction([$this, 'unapprovedRoomLeave'], $identifier);
            }, $leaveDelay);
        }
    }

    private function removeScheduledActionsForUnapprovedRoom(Identifier $identifier)
    {
        foreach ($this->timerWatchers[$identifier->getIdentString()] ?? [] as $watcherId) {
            cancel($watcherId);
        }

        unset($this->timerWatchers[$identifier->getIdentString()]);
    }

    private function connectRoom(Identifier $identifier)
    {
        $room = yield $this->connector->connect($identifier, $this, isset($this->permanentRooms[$identifier->getIdentString()]));
        $this->connectedRooms->add($room);

        yield $this->eventDispatcher->processConnect($identifier);

        return $room;
    }

    private function reconnectRoom(Identifier $identifier)
    {
        $attempt = 1;

        do {
            try {
                $this->logger->log(Level::DEBUG, "Reconnect to {$identifier}: attempt {$attempt}");
                yield from $this->connectRoom($identifier);
                return;
            } catch (\Exception $e) { // *not* Throwable on purpose! If we get one of those we should probably just bail.
                $retryIn = min($attempt * 5, 60);
                $this->logger->log(
                    Level::DEBUG,
                    "Connection to {$identifier} failed! Retrying in {$retryIn} seconds."
                    . " The error was: " . trim($e->getMessage())
                );
                yield new Pause($retryIn * 1000);
            }
        } while ($attempt++ < self::MAX_RECONNECT_ATTEMPTS);
    }

    private function restoreTransientRoom(Identifier $identifier)
    {
        yield from $this->connectRoom($identifier);

        if (!yield $this->storage->isApproved($identifier)) {
            yield from $this->scheduleActionsForUnapprovedRoom($identifier);
        }
    }

    private function leaveRoom(Room $room): Promise
    {
        assert(!$room->isPermanent(), new InvalidRoomException('Cannot leave a permanent room'));

        $this->storage->setApproved($room->getIdentifier(), false);
        $this->removeScheduledActionsForUnapprovedRoom($room->getIdentifier());

        $body = (new FormBody)
            ->addField('fkey', $room->getSession()->getFKey())
            ->addField('quiet', 'true');

        $request = (new HttpRequest)
            ->setMethod('POST')
            ->setUri($this->urlResolver->getEndpointURL($room, Endpoint::CHATROOM_LEAVE))
            ->setBody($body);

        $this->storage->removeRoom($room->getIdentifier());
        $room->getWebsocketHandler()->getEndpoint()->close();

        return $this->httpClient->request($request);
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

        if (yield $this->storage->containsApproveVote($identifier, $userID)) {
            throw new UserAlreadyVotedException(
                "User #{$userID} has already cast an approve vote for {$identifier->getIdentString()}"
            );
        }

        yield $this->storage->addApproveVote($identifier, $userID);

        $requiredVotes = yield $this->getRequiredApproveVoteCount($identifier);
        $currentVotes = count(yield $this->storage->getApproveVotes($identifier));

        if ($currentVotes < $requiredVotes) {
            return [false, $currentVotes];
        }

        $this->removeScheduledActionsForUnapprovedRoom($identifier);
        yield $this->storage->setApproved($identifier, true);

        return [true, $currentVotes];
    }

    private function checkAndAddLeaveVote(Identifier $identifier, int $userID)
    {
        $room = $this->connectedRooms->get($identifier);

        if (!yield $this->chatClient->isRoomOwner($identifier, $userID)) {
            throw new UserNotAcceptableException(
                "User #{$userID} is not a room owner of {$identifier->getIdentString()}"
            );
        }

        if (yield $this->storage->containsLeaveVote($identifier, $userID)) {
            throw new UserAlreadyVotedException(
                "User #{$userID} has already cast a leave vote for {$identifier->getIdentString()}"
            );
        }

        yield $this->storage->addLeaveVote($identifier, $userID);

        $requiredVotes = yield $this->getRequiredLeaveVoteCount($identifier);
        $currentVotes = count(yield $this->storage->getLeaveVotes($identifier));

        if ($currentVotes < $requiredVotes) {
            return [false, $currentVotes];
        }

        yield $this->chatClient->postMessage($room, "I've outstayed my welcome so I'm leaving. Bye!", PostFlags::FORCE);
        yield $this->leaveRoom($room);

        return [true, $currentVotes];
    }

    private function checkAndAddRoom(Identifier $identifier, int $invitingUserID)
    {
        if (isset($this->permanentRooms[$identifier->getIdentString()]) || yield $this->storage->containsRoom($identifier)) {
            throw new RoomAlreadyExistsException("Already present in {$identifier->getIdentString()}");
        }

        /** @var Room $room */
        $room = yield from $this->connectRoom($identifier);
        yield $this->storage->addRoom($identifier, time());
        yield from $this->scheduleActionsForUnapprovedRoom($identifier);

        $isApproved = false;
        $currentVotes = 0;
        $requiredVotes = yield $this->getRequiredApproveVoteCount($identifier);

        try {
            list($isApproved, $currentVotes) = yield from $this->checkAndAddApproveVote($identifier, $invitingUserID);
        }
        catch (AlreadyApprovedException $e) { /* this should never happen but just in case */ }
        catch (UserNotAcceptableException $e) { /* this can happen but we don't care */ }

        /** @var ChatUser $invitingUser */
        $botUser = $room->getSession()->getUser();
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

    private function processDisconnectAndReconnectIfNecessary(Identifier $identifier)
    {
        yield $this->eventDispatcher->processDisconnect($identifier);

        assert(
            $this->connectedRooms->contains($identifier),
            new \LogicException("Got disconnect from unknown room {$identifier}")
        );

        $room = $this->connectedRooms->get($identifier);
        $this->connectedRooms->remove($identifier);

        if ($room->isPermanent() || yield $this->storage->containsRoom($identifier)) {
            yield from $this->reconnectRoom($identifier);
        }
    }

    public function getRequiredApproveVoteCount(Identifier $identifier): Promise
    {
        return resolve(function() use($identifier) {
            return min((int)ceil(count(yield $this->chatClient->getRoomOwners($identifier)) / 2), 3);
        });
    }

    public function getRequiredLeaveVoteCount(Identifier $identifier): Promise
    {
        return resolve(function() use($identifier) {
            return min(count(yield $this->chatClient->getRoomOwners($identifier)), 2);
        });
    }

    public function addRoom(Identifier $identifier, int $invitingUserID): Promise
    {
        return $this->enqueueAction([$this, 'checkAndAddRoom'], $identifier, $invitingUserID);
    }

    public function restoreRooms(array $permanentRoomIdentifiers): Promise
    {
        return resolve(function() use($permanentRoomIdentifiers) {
            /** @var Identifier $identifier */
            $promises = [];

            foreach ($permanentRoomIdentifiers as $identifier) {
                $this->permanentRooms[$identifier->getIdentString()] = true;
                $promises[] = resolve($this->connectRoom($identifier));
            }

            $transientRoomIdentifiers = array_map(function($ident) {
                return $this->identifierFactory->createFromIdentString($ident);
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

    public function addLeaveVote(Identifier $identifier, int $userID): Promise
    {
        return $this->enqueueAction([$this, 'checkAndAddLeaveVote'], $identifier, $userID);
    }

    public function processDisconnect(Identifier $identifier): Promise
    {
        return $this->enqueueAction([$this, 'processDisconnectAndReconnectIfNecessary'], $identifier);
    }

    public function isApproved(Identifier $identifier): Promise
    {
        return isset($this->permanentRooms[$identifier->getIdentString()])
            ? new Success(true)
            : $this->enqueueAction([$this, 'checkIfRoomIsApproved'], $identifier);
    }
}
