<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\EventHandlers;

use Amp\Promise;
use Psr\Log\LoggerInterface as Logger;
use Room11\Jeeves\Chat\PresenceManager;
use Room11\Jeeves\System\BuiltInEventHandler;
use Room11\StackChat\Client\Client;
use Room11\StackChat\Event\Event;
use Room11\StackChat\Event\EventType;
use Room11\StackChat\Event\Invitation;
use Room11\StackChat\Room\IdentifierFactory as ChatRoomIdentifierFactory;

class Invite implements BuiltInEventHandler
{
    private $identifierFactory;
    private $presenceManager;
    private $logger;

    public function __construct(
        ChatRoomIdentifierFactory $identifierFactory,
        Client $chatClient,
        PresenceManager $presenceManager,
        Logger $logger
    ) {
        $this->identifierFactory = $identifierFactory;
        $this->presenceManager = $presenceManager;
        $this->logger = $logger;
    }

    public function handleEvent(Event $event): Promise
    {
        /** @var Invitation $event */
        $userId = $event->getUserId();
        $userName = $event->getUserName();
        $identifier = $this->identifierFactory->create($event->getRoomId(), $event->getHost());

        $this->logger->debug("Invited to {$identifier} by {$userName} (#{$userId})");

        return $this->presenceManager->addRoom($identifier, $userId);
    }

    public function getEventTypes(): array
    {
        return [EventType::INVITATION];
    }
}
