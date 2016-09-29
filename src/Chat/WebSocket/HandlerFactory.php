<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\WebSocket;

use Room11\Jeeves\Chat\Event\Builder as EventBuilder;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\PresenceManager;
use Room11\Jeeves\Log\Logger;

class HandlerFactory
{
    private $eventBuilder;
    private $eventDispatcher;
    private $logger;

    public function __construct(
        EventBuilder $eventBuilder,
        EventDispatcher $eventDispatcher,
        Logger $logger
    ) {
        $this->eventBuilder = $eventBuilder;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    public function build(ChatRoomIdentifier $identifier, PresenceManager $presenceManager)
    {
        return new Handler(
            $this->eventBuilder, $this->eventDispatcher, $this->logger,
            $presenceManager, $identifier
        );
    }
}
