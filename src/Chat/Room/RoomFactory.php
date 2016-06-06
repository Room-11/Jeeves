<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Room;

use Amp\Websocket\Endpoint as WebsocketEndpoint;
use Room11\Jeeves\Storage\KeyValueFactory as KeyValueStorageFactory;

class RoomFactory
{
    private $keyValueStorageFactory;

    public function __construct(KeyValueStorageFactory $keyValueStorageFactory)
    {
        $this->keyValueStorageFactory = $keyValueStorageFactory;
    }

    public function build(Identifier $identifier, SessionInfo $sessionInfo, WebsocketEndpoint $endpoint)
    {
        $keyValueStore = $this->keyValueStorageFactory->build($identifier->getIdentString());

        return new Room($identifier, $sessionInfo, $endpoint, $keyValueStore);
    }
}
