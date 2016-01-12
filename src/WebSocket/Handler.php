<?php declare(strict_types=1);

namespace Room11\Jeeves\WebSocket;

use Room11\Jeeves\Chat\Message\Factory as MessageFactory;
use Room11\Jeeves\Chat\Command\Collection as CommandCollection;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Log\Level;
use Amp\Websocket;
use Room11\Jeeves\Chat\Message\Unknown;

class Handler implements Websocket
{
    private $messageFactory;

    private $commands;

    private $logger;

    public function __construct(MessageFactory $messageFactory, CommandCollection $commands, Logger $logger)
    {
        $this->messageFactory = $messageFactory;
        $this->commands       = $commands;
        $this->logger         = $logger;
    }

    public function onOpen(Websocket\Endpoint $endpoint, array $headers)
    {
        $this->logger->log(Level::DEBUG, 'Connection established');
    }

    public function onData(Websocket\Message $msg): \Generator
    {
        $rawMessage = yield $msg;

        $message = $this->messageFactory->build(json_decode($rawMessage, true));

        $this->logger->log(Level::MESSAGE, 'Message received', [
            'rawMessage' => $rawMessage,
            'message'    => $message,
        ]);

        if ($message instanceof Unknown) {
            $this->logger->log(Level::UNKNOWN_MESSAGE, 'Unknown message received', $rawMessage);
        }

        yield from $this->commands->handle($message);

    }

    public function onClose($code, $reason)
    {
        var_dump(yield $reason);
    }
}
