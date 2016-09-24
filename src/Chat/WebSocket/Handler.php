<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\WebSocket;

use Amp\Pause;
use Amp\Websocket;
use Amp\Websocket\Endpoint as WebSocketEndpoint;
use Amp\Websocket\Message as WebSocketMessage;
use ExceptionalJSON\DecodeErrorException as JSONDecodeErrorException;
use Room11\Jeeves\Chat\Event\Builder as EventBuilder;
use Room11\Jeeves\Chat\Event\Event;
use Room11\Jeeves\Chat\Event\GlobalEvent;
use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Event\RoomSourcedEvent;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Message\Factory as MessageFactory;
use Room11\Jeeves\Chat\Room\Collection as ChatRoomCollection;
use Room11\Jeeves\Chat\Room\Connector as ChatRoomConnector;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Chat\Room\RoomFactory as ChatRoomFactory;
use Room11\Jeeves\Chat\Room\SessionInfo;
use Room11\Jeeves\Chat\Room\SessionInfo as ChatRoomSessionInfo;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Storage\Room as RoomStorage;
use Room11\Jeeves\System\BuiltInActionManager;
use Room11\Jeeves\System\PluginManager;
use function Amp\cancel;
use function Amp\info;
use function Amp\once;

class Handler implements Websocket
{
    const MAX_RECONNECT_ATTEMPTS = 1500; // a little over 1 day, in practice

    const HEARTBEAT_TIMEOUT_SECONDS = 40;

    private $eventBuilder;
    private $messageFactory;
    private $roomConnector;
    private $roomFactory;
    private $rooms;
    private $builtInActionManager;
    private $pluginManager;
    private $globalEventDispatcher;
    private $logger;
    private $roomIdentifier;
    private $roomStorage;
    private $permanent;
    private $devMode;

    /**
     * @var SessionInfo
     */
    private $sessionInfo;

    /**
     * @var ChatRoom
     */
    private $room;

    private $timeoutWatcherId;

    public function __construct(
        EventBuilder $eventBuilder,
        MessageFactory $messageFactory,
        ChatRoomConnector $roomConnector,
        ChatRoomFactory $roomFactory,
        ChatRoomCollection $rooms,
        BuiltInActionManager $builtInActionManager,
        PluginManager $pluginManager,
        GlobalEventDispatcher $globalEventDispatcher,
        Logger $logger,
        ChatRoomIdentifier $roomIdentifier,
        RoomStorage $roomStorage,
        bool $permanent,
        bool $devMode
    ) {
        $this->eventBuilder = $eventBuilder;
        $this->messageFactory = $messageFactory;
        $this->roomConnector = $roomConnector;
        $this->roomFactory = $roomFactory;
        $this->rooms = $rooms;
        $this->builtInActionManager = $builtInActionManager;
        $this->pluginManager = $pluginManager;
        $this->globalEventDispatcher = $globalEventDispatcher;
        $this->logger = $logger;
        $this->roomIdentifier = $roomIdentifier;
        $this->roomStorage = $roomStorage;
        $this->permanent = $permanent;
        $this->devMode = $devMode;
    }

    private function clearTimeoutWatcher()
    {
        if ($this->timeoutWatcherId !== null) {
            $this->logger->log(Level::DEBUG, "Cancelling timeout watcher #{$this->timeoutWatcherId}");

            cancel($this->timeoutWatcherId);
            $this->timeoutWatcherId = null;
        }
    }

    private function setTimeoutWatcher(int $secs = self::HEARTBEAT_TIMEOUT_SECONDS)
    {
        $this->timeoutWatcherId = once(function() {
            $this->logger->log(Level::DEBUG, "Connection to {$this->roomIdentifier} timed out");

            $this->room->getWebsocketEndpoint()->close();
        }, $secs * 1000);

        $this->logger->log(Level::DEBUG, "Created timeout watcher #{$this->timeoutWatcherId}");
    }

    private function processEvent(Event $event): \Generator
    {
        $eventId = $event->getId();
        $this->logger->log(Level::EVENT, "Processing room event #{$eventId}", $event);

        try {
            $this->logger->log(Level::DEBUG, "Processing room event #{$eventId} for built in event handlers");
            yield $this->builtInActionManager->handleEvent($event);
            $this->logger->log(Level::DEBUG, "Event #{$eventId} processed for built in event handlers");

            $chatMessage = null;

            if ($event instanceof MessageEvent && ($this->devMode || $event->getUserId() !== $this->room->getSessionInfo()->getUser()->getId())) {
                $chatMessage = $this->messageFactory->build($event);

                if ($chatMessage instanceof Command) {
                    $this->logger->log(Level::DEBUG, "Processing room event #{$eventId} for built in commands");
                    yield $this->builtInActionManager->handleCommand($chatMessage);
                    $this->logger->log(Level::DEBUG, "Event #{$eventId} processed for built in commands");
                }
            }

            if (!$event instanceof RoomSourcedEvent) { // probably an Unknown event
                return;
            }

            $this->logger->log(Level::DEBUG, "Processing room event #{$eventId} for plugins");
            yield $this->pluginManager->handleRoomEvent($event, $chatMessage);
            $this->logger->log(Level::DEBUG, "Event #{$eventId} processed for plugins");
        } catch (\Throwable $e) {
            $this->logger->log(Level::DEBUG, "Something went wrong while processing event #{$eventId}: $e");
        }
    }

    public function getRoomIdentifier(): ChatRoomIdentifier
    {
        return $this->roomIdentifier;
    }

    public function getRoom(): ChatRoom
    {
        return $this->room;
    }

    public function setSessionInfo(ChatRoomSessionInfo $sessionInfo)
    {
        $this->sessionInfo = $sessionInfo;
    }

    public function onOpen(WebsocketEndpoint $endpoint, array $headers): \Generator {
        try {
            $this->logger->log(Level::DEBUG, "Connection to {$this->roomIdentifier} established");

            // we expect a heartbeat message from the server immediately on connect, if we don't get one then try again
            // this seems to happen a lot while testing, I'm not sure if it's an issue with the server or us (it's
            // probably us)
            $this->setTimeoutWatcher(2);

            $this->room = $this->roomFactory->build($this->roomIdentifier, $this->sessionInfo, $this->roomStorage, $this->permanent, $endpoint);
            $this->rooms->add($this->room);

            yield $this->pluginManager->enableAllPluginsForRoom($this->room);
        } catch (\Throwable $e) {
            $this->logger->log(
                Level::DEBUG, "Something went generally wrong while opening connection to {$this->roomIdentifier}: $e"
            );
        }
    }

    public function onData(WebsocketMessage $websocketMessage): \Generator {
        try {
            $rawWebsocketMessage = yield $websocketMessage;

            $this->logger->log(Level::DEBUG, "Websocket message received on connection to {$this->roomIdentifier}", $rawWebsocketMessage);

            $this->clearTimeoutWatcher();
            $this->setTimeoutWatcher();

            try {
                $data = json_try_decode($rawWebsocketMessage, true);
            } catch (JSONDecodeErrorException $e) {
                $this->logger->log(Level::ERROR, "Error decoding JSON message from server: {$e->getMessage()}");
                return;
            }

            /** @var Event[] $events */
            $events = yield from $this->eventBuilder->build($data, $this);
            $this->logger->log(Level::DEBUG, count($events) . " events targeting {$this->roomIdentifier} to process");

            foreach ($events as $event) {
                yield from ($event instanceof GlobalEvent)
                    ? $this->globalEventDispatcher->processEvent($event)
                    : $this->processEvent($event);
            }
        } catch (\Throwable $e) {
            $this->logger->log(
                Level::DEBUG, "Something went generally wrong while processing events for {$this->roomIdentifier}: $e"
            );
        }
    }

    public function onClose($code, $reason) {
        try {
            $this->clearTimeoutWatcher();

            $this->logger->log(Level::DEBUG, "Connection to {$this->roomIdentifier} closed", info());
            $this->pluginManager->disableAllPluginsForRoom($this->room);

            if (!$this->rooms->contains($this->room)) {
                return;
            }

            $this->rooms->remove($this->room);
            $this->sessionInfo = $this->room = null;

            $attempt = 1;

            do {
                try {
                    $this->logger->log(Level::DEBUG, "Attempting to reconnect to {$this->roomIdentifier}");
                    yield from $this->roomConnector->connect($this);
                    return;
                } catch (\Exception $e) { // *not* Throwable on purpose! If we get one of those we should probably just bail.
                    $retryIn = min($attempt * 5, 60);
                    $this->logger->log(Level::DEBUG, "Connection attempt #{$attempt} failed! Retrying in {$retryIn} seconds. The error was: " . trim($e->getMessage()));
                    yield new Pause($retryIn * 1000);
                }
            } while ($attempt++ < self::MAX_RECONNECT_ATTEMPTS);
        } catch (\Throwable $e) {
            $this->logger->log(
                Level::DEBUG, "Something went generally wrong while handling closure of connection to {$this->roomIdentifier}: $e"
            );
        }
    }
}
