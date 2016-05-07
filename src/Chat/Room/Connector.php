<?php

namespace Room11\Jeeves\Chat\Room;

use Room11\Jeeves\Chat\Room\Collection as ChatRoomCollection;
use Room11\Jeeves\WebSocket\Collection as WebSocketCollection;
use Room11\Jeeves\WebSocket\HandlerFactory as WebSocketHandlerFactory;
use Room11\Jeeves\WebSocket\HandshakeFactory as WebSocketHandshakeFactory;
use function Amp\websocket;

class Connector
{
    private $authenticator;
    private $rooms;
    private $sockets;
    private $handshakeFactory;
    private $handlerFactory;

    private $counter = 0;

    public function __construct(
        Authenticator $authenticator,
        WebSocketHandshakeFactory $handshakeFactory,
        WebSocketHandlerFactory $handlerFactory,
        ChatRoomCollection $rooms,
        WebSocketCollection $sockets
    ) {
        $this->authenticator = $authenticator;
        $this->handshakeFactory = $handshakeFactory;
        $this->handlerFactory = $handlerFactory;
        $this->rooms = $rooms;
        $this->sockets = $sockets;
    }

    private function getNextId()
    {
        do {
            $id = $this->counter++;
        } while ($this->sockets->contains($id));

        return $id;
    }

    public function connect(Identifier $identifier): \Generator
    {
        /** @var Room $room */
        $room = yield from $this->authenticator->logIn($identifier);

        $id = $this->getNextId();

        $handshake =  $this->handshakeFactory->build($room->getWebSocketURL())
            ->setHeader('Origin', $identifier->getOriginURL('http'));

        $handler = $this->handlerFactory->build($room, $id);

        $this->sockets->add($id, websocket($handler, $handshake));
        $this->rooms->add($room);

        return $room;
    }
}
