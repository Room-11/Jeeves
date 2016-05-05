<?php declare(strict_types = 1);

namespace Room11\Jeeves\WebSocket;

use Amp\Websocket;
use Room11\Jeeves\Chat\BuiltInCommandManager;
use Room11\Jeeves\Chat\Event\Factory as EventFactory;
use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Event\Unknown;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\PluginManager;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;

class Handler implements Websocket {
    private $eventFactory;
    private $builtInCommandManager;
    private $pluginManager;
    private $room;
    private $logger;

    public function __construct(
        EventFactory $eventFactory,
        BuiltInCommandManager $builtIns,
        PluginManager $plugins,
        Logger $logger,
        ChatRoom $room
    ) {
        $this->eventFactory = $eventFactory;
        $this->pluginManager = $plugins;
        $this->builtInCommandManager = $builtIns;
        $this->logger = $logger;
        $this->room = $room;
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

            if ($event instanceof MessageEvent && ($message = $event->getMessage()) instanceof Command) {
                /** @var Command $message */
                yield from $this->builtInCommandManager->handle($message);
            }

            yield from $this->pluginManager->handle($event);
        }
    }

    public function onClose($code, $reason) {
        // TODO: Log message and exit / reconnect
        var_dump(yield $reason);
    }
}
