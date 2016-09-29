<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\EventHandlers;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Event\Event;
use Room11\Jeeves\Chat\Event\EventType;
use Room11\Jeeves\Chat\Event\Invitation;
use Room11\Jeeves\Chat\Room\IdentifierFactory as ChatRoomIdentifierFactory;
use Room11\Jeeves\Chat\Room\PresenceManager;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\System\BuiltInEventHandler;

class Invite implements BuiltInEventHandler
{
    private $identifierFactory;
    private $chatClient;
    private $presenceManager;
    private $logger;

    public function __construct(
        ChatRoomIdentifierFactory $identifierFactory,
        ChatClient $chatClient,
        PresenceManager $presenceManager,
        Logger $logger
    ) {
        $this->identifierFactory = $identifierFactory;
        $this->chatClient = $chatClient;
        $this->presenceManager = $presenceManager;
        $this->logger = $logger;
    }

    public function handleEvent(Event $event): Promise
    {
        /** @var Invitation $event */
        $userId = $event->getUserId();
        $userName = $event->getUserName();
        $identifier = $this->identifierFactory->create($event->getRoomId(), $event->getHost());

        $this->logger->log(Level::DEBUG, "Invited to {$identifier} by {$userName} (#{$userId})");

        return $this->presenceManager->addRoom($identifier, $userId);
    }

    public function getEventTypes(): array
    {
        return [EventType::INVITATION];
    }
}
