<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Room;

use Amp\Promise;
use Room11\Jeeves\Chat\WebSocket\HandlerFactory as WebSocketHandlerFactory;
use Room11\Jeeves\Chat\WebSocket\HandshakeFactory as WebSocketHandshakeFactory;
use function Amp\resolve;
use function Amp\websocket;

class Connector
{
    private $authenticator;
    private $roomFactory;
    private $handshakeFactory;
    private $handlerFactory;

    public function __construct(
        Authenticator $authenticator,
        RoomFactory $roomFactory,
        WebSocketHandshakeFactory $handshakeFactory,
        WebSocketHandlerFactory $handlerFactory
    ) {
        $this->authenticator = $authenticator;
        $this->roomFactory = $roomFactory;
        $this->handshakeFactory = $handshakeFactory;
        $this->handlerFactory = $handlerFactory;
    }

    public function connect(Identifier $identifier, PresenceManager $presenceManager, bool $permanent): Promise
    {
        return resolve(function() use($identifier, $presenceManager, $permanent) {
            /** @var SessionInfo $sessionInfo */
            $sessionInfo = yield $this->authenticator->getRoomSessionInfo($identifier);

            $handshake = $this->handshakeFactory->build($sessionInfo->getWebSocketUrl())
                ->setHeader('Origin', 'https://' . $identifier->getHost());
            $handler = $this->handlerFactory->build($identifier, $presenceManager);

            yield websocket($handler, $handshake);

            return $this->roomFactory->build($identifier, $sessionInfo, $handler, $presenceManager, $permanent);
        });
    }
}
