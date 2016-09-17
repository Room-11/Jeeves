<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\WebSocket;

use Room11\Jeeves\Chat\Event\Builder as EventBuilder;
use Room11\Jeeves\Chat\Message\Factory as MessageFactory;
use Room11\Jeeves\Chat\Room\Collection as ChatRoomCollection;
use Room11\Jeeves\Chat\Room\Connector as ChatRoomConnector;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\RoomFactory as ChatRoomFactory;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\System\BuiltInCommandManager;
use Room11\Jeeves\System\PluginManager;

class HandlerFactory
{
    private $eventBuilder;
    private $messageFactory;
    private $roomConnector;
    private $roomFactory;
    private $rooms;
    private $logger;
    private $builtInCommandManager;
    private $pluginManager;
    private $devMode;

    public function __construct(
        EventBuilder $eventBuilder,
        MessageFactory $messageFactory,
        ChatRoomConnector $roomConnector,
        ChatRoomFactory $roomFactory,
        ChatRoomCollection $rooms,
        BuiltInCommandManager $builtInCommandManager,
        PluginManager $pluginManager,
        Logger $logger,
        bool $devMode
    ) {
        $this->eventBuilder = $eventBuilder;
        $this->messageFactory = $messageFactory;
        $this->roomConnector = $roomConnector;
        $this->roomFactory = $roomFactory;
        $this->rooms = $rooms;
        $this->logger = $logger;
        $this->builtInCommandManager = $builtInCommandManager;
        $this->pluginManager = $pluginManager;
        $this->devMode = $devMode;
    }

    public function build(ChatRoomIdentifier $identifier)
    {
        return new Handler(
            $this->eventBuilder, $this->messageFactory, $this->roomConnector, $this->roomFactory, $this->rooms,
            $this->builtInCommandManager, $this->pluginManager, $this->logger, $identifier, $this->devMode
        );
    }
}
