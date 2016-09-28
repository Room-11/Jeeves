<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Room;

use Amp\Websocket\Endpoint as WebsocketEndpoint;
use Room11\Jeeves\Storage\KeyValueFactory as KeyValueStorageFactory;
use Room11\Jeeves\Storage\Room as RoomStorage;

class RoomFactory
{
    private $keyValueStorageFactory;

    public function __construct(KeyValueStorageFactory $keyValueStorageFactory)
    {
        $this->keyValueStorageFactory = $keyValueStorageFactory;
    }

    public function build(Identifier $identifier, SessionInfo $sessionInfo, bool $permanent, WebsocketEndpoint $endpoint, PresenceManager $presenceManager)
    {
        $keyValueStore = $this->keyValueStorageFactory->build($identifier->getIdentString());

        return new Room($identifier, $sessionInfo, $presenceManager, $permanent, $endpoint, $keyValueStore);
    }
}
