<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\EventHandlers;

use Amp\Pause;
use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\DataFetchFailureException;
use Room11\Jeeves\Chat\Event\Event;
use Room11\Jeeves\Chat\Event\EventType;
use Room11\Jeeves\Chat\Event\Invitation;
use Room11\Jeeves\Chat\Room\Connector as ChatRoomConnector;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\IdentifierFactory as ChatRoomIdentifierFactory;
use Room11\Jeeves\Chat\WebSocket\HandlerFactory as WebSocketHandlerFactory;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\System\BuiltInEventHandler;
use Room11\Jeeves\Storage\Room as RoomStorage;
use function Amp\resolve;

class Invite implements BuiltInEventHandler
{
    private $identifierFactory;
    private $handlerFactory;
    private $connector;
    private $chatClient;
    private $storage;
    private $logger;

    public function __construct(
        ChatRoomIdentifierFactory $identifierFactory,
        WebSocketHandlerFactory $handlerFactory,
        ChatRoomConnector $connector,
        ChatClient $chatClient,
        RoomStorage $storage,
        Logger $logger
    ) {
        $this->identifierFactory = $identifierFactory;
        $this->handlerFactory = $handlerFactory;
        $this->connector = $connector;
        $this->chatClient = $chatClient;
        $this->storage = $storage;
        $this->logger = $logger;
    }

    public function handleEvent(Event $event): Promise
    {
        /** @var Invitation $event */
        $userId = $event->getUserId();

        $this->logger->log(Level::DEBUG, "Invited to {$event->getRoomName()} by {$event->getUserName()} (#{$userId})");

        $sourceIdentifier = $event->getSourceHandler()->getRoomIdentifier();
        $destIdentifier = $this->identifierFactory->create(
            $event->getRoomId(),
            $sourceIdentifier->getHost(),
            true
        );

        return resolve(function() use($destIdentifier, $userId) {
            yield $this->storage->addRoom($destIdentifier, time());

            $handler = $this->handlerFactory->build($destIdentifier);
            yield from $this->connector->connect($handler);

            if (yield $this->chatClient->isRoomOwner($destIdentifier, $userId)) {
                yield $this->storage->addApproveVote($destIdentifier, $userId);
            }
        });
    }

    public function getEventTypes(): array
    {
        return [EventType::INVITATION];
    }
}
