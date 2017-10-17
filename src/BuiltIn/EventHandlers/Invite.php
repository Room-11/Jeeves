<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\EventHandlers;

use Amp\Promise;
use Psr\Log\LoggerInterface as Logger;
use Room11\Jeeves\Chat\PresenceManager;
use Room11\Jeeves\System\BuiltInEventHandler;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Event\Event;
use Room11\StackChat\Event\EventType;
use Room11\StackChat\Event\Invitation;
use Room11\StackChat\Room\Room;

class Invite implements BuiltInEventHandler
{
    private $presenceManager;
    private $logger;

    public function __construct(
        ChatClient $chatClient,
        PresenceManager $presenceManager,
        Logger $logger
    ) {
        $this->presenceManager = $presenceManager;
        $this->logger = $logger;
    }

    public function handleEvent(Event $event): Promise
    {
        /** @var Invitation $event */
        $userId = $event->getUserId();
        $userName = $event->getUserName();
        $room = new Room($event->getRoomId(), $event->getHost());

        $this->logger->debug("Invited to {$room} by {$userName} (#{$userId})");

        return $this->presenceManager->addRoom($room, $userId);
    }

    public function getEventTypes(): array
    {
        return [EventType::INVITATION];
    }
}
