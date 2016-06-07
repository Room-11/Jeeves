<?php declare(strict_types = 1);

namespace Room11\Jeeves\WebSocket;

use Amp\Pause;
use Amp\Promise;
use Amp\Websocket;
use Amp\Websocket\Endpoint as WebSocketEndpoint;
use Amp\Websocket\Message as WebSocketMessage;
use ExceptionalJSON\DecodeErrorException as JSONDecodeErrorException;
use Room11\Jeeves\Chat\BuiltInCommandManager;
use Room11\Jeeves\Chat\Event\Builder as EventBuilder;
use Room11\Jeeves\Chat\Event\Event;
use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Event\Unknown;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Message\Factory as MessageFactory;
use Room11\Jeeves\Chat\PluginManager;
use Room11\Jeeves\Chat\Room\Collection as ChatRoomCollection;
use Room11\Jeeves\Chat\Room\Connector as ChatRoomConnector;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Chat\Room\RoomFactory as ChatRoomFactory;
use Room11\Jeeves\Chat\Room\SessionInfo;
use Room11\Jeeves\Chat\Room\SessionInfo as ChatRoomSessionInfo;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use function Amp\cancel;
use function Amp\info;
use function Amp\once;

class Handler implements Websocket
{
    const MAX_RECONNECT_ATTEMPTS = 1500; // a little over 1 day, in practice

    private $eventFactory;
    private $messageFactory;
    private $roomConnector;
    private $roomFactory;
    private $rooms;
    private $builtInCommandManager;
    private $pluginManager;
    private $logger;
    private $roomIdentifier;

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
        EventBuilder $eventFactory,
        MessageFactory $messageFactory,
        ChatRoomConnector $roomConnector,
        ChatRoomFactory $roomFactory,
        ChatRoomCollection $rooms,
        BuiltInCommandManager $builtInCommandManager,
        PluginManager $pluginManager,
        Logger $logger,
        ChatRoomIdentifier $roomIdentifier
    ) {
        $this->eventFactory = $eventFactory;
        $this->messageFactory = $messageFactory;
        $this->roomConnector = $roomConnector;
        $this->roomFactory = $roomFactory;
        $this->pluginManager = $pluginManager;
        $this->builtInCommandManager = $builtInCommandManager;
        $this->logger = $logger;
        $this->rooms = $rooms;
        $this->roomIdentifier = $roomIdentifier;
    }

    private function clearTimeoutWatcher()
    {
        if ($this->timeoutWatcherId !== null) {
            $this->logger->log(Level::DEBUG, "Cancelling timeout watcher #{$this->timeoutWatcherId}");

            cancel($this->timeoutWatcherId);
            $this->timeoutWatcherId = null;
        }
    }

    private function setTimeoutWatcher()
    {
        $this->timeoutWatcherId = once(function() {
            $this->logger->log(Level::DEBUG, "Connection to {$this->roomIdentifier} timed out");

            $this->room->getWebsocketEndpoint()->close();
        }, 40000);

        $this->logger->log(Level::DEBUG, "Created timeout watcher #{$this->timeoutWatcherId}");
    }

    public function getRoomIdentifier(): ChatRoomIdentifier
    {
        return $this->roomIdentifier;
    }

    public function setSessionInfo(ChatRoomSessionInfo $sessionInfo)
    {
        $this->sessionInfo = $sessionInfo;
    }

    public function onOpen(WebsocketEndpoint $endpoint, array $headers): Promise {
        $this->logger->log(Level::DEBUG, "Connection to {$this->roomIdentifier} established");

        $this->setTimeoutWatcher();

        $this->room = $this->roomFactory->build($this->roomIdentifier, $this->sessionInfo, $endpoint);
        $this->rooms->add($this->room);

        return $this->pluginManager->enableAllPluginsForRoom($this->room);
    }

    public function onData(WebsocketMessage $websocketMessage): \Generator {
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
        $events = yield from $this->eventFactory->build($data, $this->room);
        $this->logger->log(Level::DEBUG, count($events) . " events targeting {$this->roomIdentifier} to process");

        foreach ($events as $event) {
            $eventId = $event->getId();
            $this->logger->log(Level::EVENT, "Processing event #{$eventId}", $event);

            if ($event instanceof Unknown) {
                $this->logger->log(Level::UNKNOWN_EVENT, "Unknown event received", $event);
                continue;
            }

            $chatMessage = null;
            if ($event instanceof MessageEvent && $event->getUserId() !== $this->room->getSessionInfo()->getUserId()) {
                $chatMessage = $this->messageFactory->build($event);

                if ($chatMessage instanceof Command) {
                    $this->logger->log(Level::DEBUG, "Processing event #{$eventId} for built in commands");
                    yield $this->builtInCommandManager->handleCommand($chatMessage);
                    $this->logger->log(Level::DEBUG, "Event #{$eventId} processed for built in commands");
                }
            }

            $this->logger->log(Level::DEBUG, "Processing event #{$eventId} for plugins");
            yield $this->pluginManager->handleRoomEvent($event, $chatMessage);
            $this->logger->log(Level::DEBUG, "Event #{$eventId} processed for plugins");
        }
    }

    public function onClose($code, $reason) {
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
    }
}
