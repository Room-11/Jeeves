<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\WebSocket;

use Room11\Jeeves\Chat\Event\Builder as EventBuilder;
use Room11\Jeeves\Chat\Room\Collection as ChatRoomCollection;
use Room11\Jeeves\Chat\Room\Connector as ChatRoomConnector;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\PresenceManager;
use Room11\Jeeves\Chat\Room\RoomFactory as ChatRoomFactory;
use Room11\Jeeves\Log\Logger;

class HandlerFactory
{
    private $eventBuilder;
    private $roomConnector;
    private $roomFactory;
    private $rooms;
    private $logger;
    private $eventDispatcher;

    public function __construct(
        EventBuilder $eventBuilder,
        EventDispatcher $eventDispatcher,
        Logger $logger,
        ChatRoomConnector $roomConnector,
        ChatRoomFactory $roomFactory,
        ChatRoomCollection $rooms
    ) {
        $this->eventBuilder = $eventBuilder;
        $this->roomConnector = $roomConnector;
        $this->roomFactory = $roomFactory;
        $this->rooms = $rooms;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function build(ChatRoomIdentifier $identifier, PresenceManager $presenceManager, bool $permanent)
    {
        return new Handler(
            $this->eventBuilder, $this->eventDispatcher, $this->logger, $this->roomConnector, $this->roomFactory, $this->rooms,
            $presenceManager, $identifier, $permanent
        );
    }
}
