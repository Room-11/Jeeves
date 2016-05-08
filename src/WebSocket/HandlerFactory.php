<?php

namespace Room11\Jeeves\WebSocket;

use Room11\Jeeves\Chat\BuiltInCommandManager;
use Room11\Jeeves\Chat\Event\Factory as EventFactory;
use Room11\Jeeves\Chat\Message\Factory as MessageFactory;
use Room11\Jeeves\Chat\PluginManager;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class HandlerFactory
{
    private $eventFactory;
    private $messageFactory;
    private $sockets;
    private $logger;
    private $builtInCommandManager;
    private $pluginManager;

    public function __construct(
        EventFactory $eventFactory,
        MessageFactory $messageFactory,
        Collection $sockets,
        Logger $logger,
        BuiltInCommandManager $builtInCommandManager,
        PluginManager $pluginManager
    ) {
        $this->eventFactory = $eventFactory;
        $this->messageFactory = $messageFactory;
        $this->sockets = $sockets;
        $this->logger = $logger;
        $this->builtInCommandManager = $builtInCommandManager;
        $this->pluginManager = $pluginManager;
    }

    public function build(ChatRoom $room, int $socketId)
    {
        return new Handler(
            $this->eventFactory, $this->messageFactory, $this->sockets,
            $this->logger, $this->builtInCommandManager, $this->pluginManager,
            $room, $socketId
        );
    }
}
