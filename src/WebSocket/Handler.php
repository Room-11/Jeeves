<?php declare(strict_types = 1);

namespace Room11\Jeeves\WebSocket;

use Amp\Promise;
use Amp\Websocket;
use Room11\Jeeves\Chat\BuiltInCommandManager;
use Room11\Jeeves\Chat\Event\Factory as EventFactory;
use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Event\Unknown;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Message\Factory as MessageFactory;
use Room11\Jeeves\Chat\PluginManager;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;

class Handler implements Websocket {
    private $eventFactory;
    private $builtInCommandManager;
    private $pluginManager;
    private $sockets;
    private $logger;

    private $room;
    private $socketId;
    /**
     * @var MessageFactory
     */
    private $messageFactory;

    public function __construct(
        EventFactory $eventFactory,
        MessageFactory $messageFactory,
        Collection $sockets,
        Logger $logger,
        BuiltInCommandManager $builtIns,
        PluginManager $pluginManager,
        ChatRoom $room,
        int $socketId
    ) {
        $this->eventFactory = $eventFactory;
        $this->messageFactory = $messageFactory;
        $this->pluginManager = $pluginManager;
        $this->builtInCommandManager = $builtIns;
        $this->logger = $logger;
        $this->sockets = $sockets;
        $this->room = $room;
        $this->socketId = $socketId;
    }

    public function onOpen(Websocket\Endpoint $endpoint, array $headers): Promise {
        $this->logger->log(Level::DEBUG, "Connection established");

        return $this->pluginManager->enableAllPluginsForRoom($this->room);
    }

    public function onData(Websocket\Message $msg): \Generator {
        $rawMessage = yield $msg;

        foreach ($this->eventFactory->build(json_decode($rawMessage, true), $this->room) as $event) {
            $this->logger->log(Level::EVENT, "Event received", [
                "rawData" => $rawMessage,
                "event" => $event,
            ]);

            if ($event instanceof Unknown) {
                $this->logger->log(Level::UNKNOWN_EVENT, "Unknown message received", $event->getJson());
                return;
            }

            $eventId = $event->getEventId();
            $message = null;
            if ($event instanceof MessageEvent) {
                $message = $this->messageFactory->build($event);

                if ($message instanceof Command) {
                    $this->logger->log(Level::DEBUG, "Processing event #{$eventId} for built in commands");
                    yield $this->builtInCommandManager->handleCommand($message);
                    $this->logger->log(Level::DEBUG, "Event #{$eventId} processed for built in commands");
                }
            }

            $this->logger->log(Level::DEBUG, "Processing event #{$eventId} for plugins");
            yield $this->pluginManager->handleRoomEvent($event, $message);
            $this->logger->log(Level::DEBUG, "Event #{$eventId} processed for plugins");
        }
    }

    public function onClose($code, $reason) {
        // todo: reconnect stuffz
        $this->sockets->remove($this->socketId);
    }
}
