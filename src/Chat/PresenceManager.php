<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Deferred;
use Amp\Pause;
use Amp\Promise;
use Ds\Queue;
use Psr\Log\LoggerInterface as Logger;
use Room11\Jeeves\Storage\Room as RoomStorage;
use Room11\Jeeves\System\PluginManager;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Client\PostFlags;
use Room11\StackChat\Endpoint;
use Room11\StackChat\EndpointURLResolver;
use Room11\StackChat\Entities\ChatUser;
use Room11\StackChat\Room\AclDataAccessor;
use Room11\StackChat\Room\ConnectedRoomCollection;
use Room11\StackChat\Room\Connector;
use Room11\StackChat\Room\Identifier;
use Room11\StackChat\Room\IdentifierFactory;
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
    private $httpClient;
    private $urlResolver;
    private $identifierFactory;
    private $connector;
    private $connectedRooms;
    private $pluginManager;
    private $logger;

    private $actionQueues = [];
    private $timerWatchers = [];

    public function __construct(
        RoomStorage $storage,
        RoomStatusManager $statusManager,
        ChatClient $chatClient,
        AclDataAccessor $aclDataAccessor,
        HttpClient $httpClient,
        EndpointURLResolver $urlResolver,
        IdentifierFactory $identifierFactory,
        Connector $connector,
        ConnectedRoomCollection $connectedRooms,
        PluginManager $pluginManager,
        Logger $logger
    ) {
        $this->storage = $storage;
        $this->statusManager = $statusManager;
        $this->chatClient = $chatClient;
        $this->aclDataAccessor = $aclDataAccessor;
        $this->httpClient = $httpClient;
        $this->urlResolver = $urlResolver;
        $this->identifierFactory = $identifierFactory;
        $this->connector = $connector;
        $this->connectedRooms = $connectedRooms;
        $this->pluginManager = $pluginManager;
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

    private function scheduleActionsForUnapprovedRoom(Identifier $identifier, int $inviteTimestamp)
    {
        $now = time();

        $leaveDelay = ($now - ($inviteTimestamp + self::UNAPPROVED_LEAVE_DELAY_SECS)) * 1000;

        if ($leaveDelay < 0) {
            $this->enqueueAction([$this, 'unapprovedRoomLeave'], $identifier);
            return;
        }

        $ident = $identifier->getIdentString();

        $this->timerWatchers[$ident]['leave'] = once(function() use($identifier) {
            unset($this->timerWatchers[$identifier->getIdentString()]['leave']);
            $this->enqueueAction([$this, 'unapprovedRoomLeave'], $identifier);
        }, $leaveDelay);

        $remind2Delay = ($now - ($inviteTimestamp + self::UNAPPROVED_REMINDER_2_DELAY_SECS)) * 1000;
        if ($remind2Delay < 0) {
            return;
        }

        $this->timerWatchers[$ident]['remind2'] = once(function() use($identifier) {
            unset($this->timerWatchers[$identifier->getIdentString()]['remind2']);
            $this->enqueueAction([$this, 'unapprovedRoomSecondReminder'], $identifier);
        }, $remind2Delay);

        $remind1Delay = ($now - ($inviteTimestamp + self::UNAPPROVED_REMINDER_1_DELAY_SECS)) * 1000;
        if ($remind1Delay < 0) {
            return;
        }

        $this->timerWatchers[$ident]['remind1'] = once(function() use($identifier) {
            unset($this->timerWatchers[$identifier->getIdentString()]['remind1']);
            $this->enqueueAction([$this, 'unapprovedRoomFirstReminder'], $identifier);
        }, $remind1Delay);
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
        $room = yield $this->connector->connect($identifier, $this->statusManager->isPermanent($identifier));
        $this->connectedRooms->add($room);

        yield $this->pluginManager->enableAllPluginsForRoom($identifier);

        return $room;
    }

    private function reconnectRoom(Identifier $identifier)
    {
        $attempt = 1;

        do {
            try {
                $this->logger->debug("Reconnect to {$identifier}: attempt {$attempt}");
                yield from $this->connectRoom($identifier);
                return;
            } catch (\Exception $e) { // *not* Throwable on purpose! If we get one of those we should probably just bail.
                $retryIn = min($attempt * 5, 60);
                $this->logger->debug(
                    "Connection to {$identifier} failed! Retrying in {$retryIn} seconds."
                    . " The error was: " . trim($e->getMessage())
                );
                yield new Pause($retryIn * 1000);
            }
        } while ($attempt++ < self::MAX_RECONNECT_ATTEMPTS);
    }

    private function restoreTransientRoom(Identifier $identifier)
    {
        $isApproved = yield $this->storage->isApproved($identifier);
        $inviteTimestamp = yield $this->storage->getInviteTimestamp($identifier);

        if (!$isApproved && time() > $inviteTimestamp + self::UNAPPROVED_LEAVE_DELAY_SECS) {
            $this->storage->removeRoom($identifier);
            return;
        }

        yield from $this->connectRoom($identifier);

        if (!$isApproved) {
            yield from $this->scheduleActionsForUnapprovedRoom($identifier, $inviteTimestamp);
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

        if (!yield $this->aclDataAccessor->isRoomOwner($identifier, $userID)) {
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

        if (!yield $this->aclDataAccessor->isRoomOwner($identifier, $userID)) {
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
        if ($this->statusManager->isPermanent($identifier) || yield $this->storage->containsRoom($identifier)) {
            throw new RoomAlreadyExistsException("Already present in {$identifier->getIdentString()}");
        }

        /** @var Room $room */
        $room = yield from $this->connectRoom($identifier);

        $inviteTimestamp = time();

        yield $this->storage->addRoom($identifier, $inviteTimestamp);
        yield from $this->scheduleActionsForUnapprovedRoom($identifier, $inviteTimestamp);

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
        if (count(yield $this->aclDataAccessor->getRoomOwners($room)) > 1) {
            $leaveMessage .= " If two room owners do this within an hour, I will leave the room.";
        }

        $messages[] = $leaveMessage;

        foreach ($messages as $message) {
            yield $this->chatClient->postMessage($room, $message, PostFlags::FORCE);
        }
    }

    private function processDisconnectAndReconnectIfNecessary(Identifier $identifier)
    {
        yield $this->pluginManager->disableAllPluginsForRoom($identifier);

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
            return min((int)ceil(count(yield $this->aclDataAccessor->getRoomOwners($identifier)) / 2), 3);
        });
    }

    public function getRequiredLeaveVoteCount(Identifier $identifier): Promise
    {
        return resolve(function() use($identifier) {
            return min(count(yield $this->aclDataAccessor->getRoomOwners($identifier)), 2);
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
}
