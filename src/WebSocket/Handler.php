<?php declare(strict_types = 1);

namespace Room11\Jeeves\WebSocket;

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

        //todo persist this
        foreach ($pluginManager->getRegisteredPlugins() as $plugin) {
            $pluginManager->enablePluginForRoom($plugin, $room);
        }
    }

    public function onOpen(Websocket\Endpoint $endpoint, array $headers) {
        $this->logger->log(Level::DEBUG, "Connection established");
    }

    public function onData(Websocket\Message $msg): \Generator {
        $rawMessage = yield $msg;

        foreach ($this->eventFactory->build(json_decode($rawMessage, true), $this->room) as $event) {
            $this->logger->log(Level::EVENT, "Event received", [
                "rawData" => $rawMessage,
                "event" => $event,
            ]);

            if ($event instanceof Unknown) {
                $this->logger->log(Level::UNKNOWN_EVENT, "Unknown message received", $rawMessage);
                return;
            }

            $message = null;
            if ($event instanceof MessageEvent) {
                $message = $this->messageFactory->build($event);

                if ($message instanceof Command) {
                    yield from $this->builtInCommandManager->handle($message);
                }
            }

            yield from $this->pluginManager->handleRoomEvent($event, $message);
        }
    }

    public function onClose($code, $reason) {
        // todo: reconnect stuffz
        $this->sockets->remove($this->socketId);
    }
}
