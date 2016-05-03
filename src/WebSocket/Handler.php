<?php declare(strict_types = 1);

namespace Room11\Jeeves\WebSocket;

use Amp\Websocket;
use Room11\Jeeves\Chat\Event\Factory as EventFactory;
use Room11\Jeeves\Chat\Event\Unknown;
use Room11\Jeeves\Chat\PluginManager;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;

class Handler implements Websocket {
    private $eventFactory;
    private $pluginManager;
    private $logger;

    public function __construct(EventFactory $eventFactory, PluginManager $plugins, Logger $logger) {
        $this->eventFactory = $eventFactory;
        $this->pluginManager = $plugins;
        $this->logger = $logger;
    }

    public function onOpen(Websocket\Endpoint $endpoint, array $headers) {
        $this->logger->log(Level::DEBUG, "Connection established");
    }

    public function onData(Websocket\Message $msg): \Generator {
        $rawMessage = yield $msg;

        foreach ($this->eventFactory->build(json_decode($rawMessage, true)) as $message) {
            $this->logger->log(Level::MESSAGE, "Message received", [
                "rawMessage" => $rawMessage,
                "message" => $message,
            ]);

            if ($message instanceof Unknown) {
                $this->logger->log(Level::UNKNOWN_MESSAGE, "Unknown message received", $rawMessage);
            }

            yield from $this->pluginManager->handle($message);
        }
    }

    public function onClose($code, $reason) {
        // TODO: Log message and exit / reconnect
        var_dump(yield $reason);
    }
}
