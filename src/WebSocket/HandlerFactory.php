<?php  declare(strict_types=1);
namespace Room11\Jeeves\WebSocket;

use Room11\Jeeves\Chat\BuiltInCommandManager;
use Room11\Jeeves\Chat\Event\Factory as EventFactory;
use Room11\Jeeves\Chat\Message\Factory as MessageFactory;
use Room11\Jeeves\Chat\PluginManager;
use Room11\Jeeves\Chat\Room\Collection as ChatRoomCollection;
use Room11\Jeeves\Chat\Room\Connector as ChatRoomConnector;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\RoomFactory as ChatRoomFactory;
use Room11\Jeeves\Log\Logger;

class HandlerFactory
{
    private $eventFactory;
    private $messageFactory;
    private $roomConnector;
    private $roomFactory;
    private $rooms;
    private $logger;
    private $builtInCommandManager;
    private $pluginManager;

    public function __construct(
        EventFactory $eventFactory,
        MessageFactory $messageFactory,
        ChatRoomConnector $roomConnector,
        ChatRoomFactory $roomFactory,
        ChatRoomCollection $rooms,
        BuiltInCommandManager $builtInCommandManager,
        PluginManager $pluginManager,
        Logger $logger
    ) {
        $this->eventFactory = $eventFactory;
        $this->messageFactory = $messageFactory;
        $this->roomConnector = $roomConnector;
        $this->roomFactory = $roomFactory;
        $this->rooms = $rooms;
        $this->logger = $logger;
        $this->builtInCommandManager = $builtInCommandManager;
        $this->pluginManager = $pluginManager;
    }

    public function build(ChatRoomIdentifier $identifier)
    {
        return new Handler(
            $this->eventFactory, $this->messageFactory, $this->roomConnector, $this->roomFactory, $this->rooms,
            $this->builtInCommandManager, $this->pluginManager, $this->logger, $identifier
        );
    }
}
