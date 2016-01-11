<?php declare(strict_types=1);

namespace Room11\Jeeves\WebSocket;

use Amp\Websocket;

class Handler implements Websocket
{
    private $messageFactory;

    private $commands;

    public function __construct($messageFactory, $commands)
    {
        $this->messageFactory = $messageFactory;
        $this->commands       = $commands;
    }

    public function onOpen(Websocket\Endpoint $endpoint, array $headers)
    {
        echo "Connection established\n\n";
    }

    public function onData(Websocket\Message $msg)
    {
        $rawMessage = yield $msg;

        $message = $this->messageFactory->build(json_decode($rawMessage, true));

        yield from $this->commands->handle($message);

    }

    public function onClose($code, $reason)
    {
        var_dump(yield $reason);
    }
}
