<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Room;

use Room11\Jeeves\WebSocket\Handler as WebSocketHandler;
use Room11\Jeeves\WebSocket\HandshakeFactory as WebSocketHandshakeFactory;
use function Amp\websocket;

class Connector
{
    private $authenticator;
    private $handshakeFactory;

    public function __construct(
        Authenticator $authenticator,
        WebSocketHandshakeFactory $handshakeFactory
    ) {
        $this->authenticator = $authenticator;
        $this->handshakeFactory = $handshakeFactory;
    }

    public function connect(WebSocketHandler $handler): \Generator
    {
        /** @var SessionInfo $sessionInfo */
        $sessionInfo = yield from $this->authenticator->getRoomSessionInfo($handler->getRoomIdentifier());
        $handler->setSessionInfo($sessionInfo);

        $handshake = $this->handshakeFactory->build($sessionInfo->getWebSocketUrl())
            ->setHeader('Origin', $handler->getRoomIdentifier()->getOriginURL('http'));

        return websocket($handler, $handshake);
    }
}
