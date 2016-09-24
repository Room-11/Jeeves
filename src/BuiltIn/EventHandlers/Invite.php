<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\EventHandlers;

use Amp\Promise;
use Room11\Jeeves\Chat\Event\Event;
use Room11\Jeeves\Chat\Event\EventType;
use Room11\Jeeves\Chat\Event\Invitation;
use Room11\Jeeves\Chat\Room\Connector as ChatRoomConnector;
use Room11\Jeeves\Chat\Room\Identifier;
use Room11\Jeeves\Chat\WebSocket\HandlerFactory as WebSocketHandlerFactory;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\System\BuiltInEventHandler;
use function Amp\resolve;

class Invite implements BuiltInEventHandler
{
    private $handlerFactory;
    private $connector;
    private $logger;

    public function __construct(WebSocketHandlerFactory $handlerFactory, ChatRoomConnector $connector, Logger $logger)
    {
        $this->handlerFactory = $handlerFactory;
        $this->connector = $connector;
        $this->logger = $logger;
    }

    public function handleEvent(Event $event): Promise
    {
        /** @var Invitation $event */
        $this->logger->log(Level::DEBUG, "Got invited to {$event->getRoomName()} by {$event->getUserName()}");

        $sourceIdentifier = $event->getSourceHandler()->getRoomIdentifier();
        $destIdentifier = new Identifier(
            $event->getRoomId(),
            $sourceIdentifier->getHost(),
            $sourceIdentifier->isSecure()
        );

        $handler = $this->handlerFactory->build($destIdentifier);

        return resolve($this->connector->connect($handler));
    }

    public function getEventTypes(): array
    {
        return [EventType::INVITATION];
    }
}
