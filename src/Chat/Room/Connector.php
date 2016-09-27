<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Room;

use Amp\Promise;
use Room11\Jeeves\Chat\WebSocket\Handler as WebSocketHandler;
use Room11\Jeeves\Chat\WebSocket\HandshakeFactory as WebSocketHandshakeFactory;
use function Amp\resolve;
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

    public function connect(WebSocketHandler $handler): Promise
    {
        return resolve(function() use($handler) {
            /** @var SessionInfo $sessionInfo */
            $sessionInfo = yield $this->authenticator->getRoomSessionInfo($handler->getRoomIdentifier());
            $handler->setSessionInfo($sessionInfo);

            $handshake = $this->handshakeFactory->build($sessionInfo->getWebSocketUrl())
                ->setHeader('Origin', $handler->getRoomIdentifier()->getOriginURL('http'));

            yield websocket($handler, $handshake);

            return $handler->getRoom();
        });
    }
}
