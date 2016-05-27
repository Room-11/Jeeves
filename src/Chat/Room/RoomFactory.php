<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Room;

use Amp\Websocket\Endpoint as WebsocketEndpoint;

class RoomFactory
{
    public function build(Identifier $identifier, SessionInfo $sessionInfo, WebsocketEndpoint $endpoint)
    {
        return new Room($identifier, $sessionInfo, $endpoint);
    }
}
