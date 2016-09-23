<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\EventHandlers;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Event\Event;
use Room11\Jeeves\Chat\Event\EventType;
use Room11\Jeeves\Chat\Event\Invitation;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\System\BuiltInEventHandler;

class Invite implements BuiltInEventHandler
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function handleEvent(Event $event): Promise
    {
        /** @var Invitation $event */
        $this->logger->log(Level::DEBUG, "Got invited to {$event->getRoomName()} by {$event->getUserName()}");

        return new Success;
    }

    public function getEventTypes(): array
    {
        return [EventType::INVITATION];
    }
}
