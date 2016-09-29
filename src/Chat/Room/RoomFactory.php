<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Room;

use Amp\Websocket\Endpoint as WebsocketEndpoint;
use Room11\Jeeves\Chat\WebSocket\Handler;
use Room11\Jeeves\Storage\KeyValueFactory as KeyValueStorageFactory;

class RoomFactory
{
    private $keyValueStorageFactory;

    public function __construct(KeyValueStorageFactory $keyValueStorageFactory)
    {
        $this->keyValueStorageFactory = $keyValueStorageFactory;
    }

    public function build(Identifier $identifier, SessionInfo $sessionInfo, Handler $handler, PresenceManager $presenceManager, bool $permanent)
    {
        $keyValueStore = $this->keyValueStorageFactory->build($identifier->getIdentString());

        return new Room($identifier, $sessionInfo, $handler, $presenceManager, $keyValueStore, $permanent);
    }
}
