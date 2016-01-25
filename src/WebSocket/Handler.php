<?php declare(strict_types = 1);

namespace Room11\Jeeves\WebSocket;

use Amp\Websocket;
use Room11\Jeeves\Chat\Message\Factory as MessageFactory;
use Room11\Jeeves\Chat\Message\Unknown;
use Room11\Jeeves\Chat\Plugin\Collection as PluginCollection;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;

class Handler implements Websocket {
    private $messageFactory;
    private $plugins;
    private $logger;

    public function __construct(MessageFactory $messageFactory, PluginCollection $plugins, Logger $logger) {
        $this->messageFactory = $messageFactory;
        $this->plugins = $plugins;
        $this->logger = $logger;
    }

    public function onOpen(Websocket\Endpoint $endpoint, array $headers) {
        $this->logger->log(Level::DEBUG, "Connection established");
    }

    public function onData(Websocket\Message $msg): \Generator {
        $rawMessage = yield $msg;

        $message = $this->messageFactory->build(json_decode($rawMessage, true));

        $this->logger->log(Level::MESSAGE, "Message received", [
            "rawMessage" => $rawMessage,
            "message" => $message,
        ]);

        if ($message instanceof Unknown) {
            $this->logger->log(Level::UNKNOWN_MESSAGE, "Unknown message received", $rawMessage);
        }

        yield from $this->plugins->handle($message);
    }

    public function onClose($code, $reason) {
        // TODO: Log message and exit / reconnect
        var_dump(yield $reason);
    }
}
