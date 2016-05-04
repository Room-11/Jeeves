<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\MessageEvent;

class Message
{
    private $event;

    public function __construct(MessageEvent $event)
    {
        $this->event = $event;
    }

    public function getEvent(): MessageEvent
    {
        return $this->event;
    }

    public function getText(): string
    {
        return html_entity_decode($this->event->getMessageContent(), ENT_QUOTES);
    }

    public function getId(): int
    {
        return $this->event->getMessageId();
    }

    public function getUserId(): int
    {
        return $this->event->getUserId();
    }

    public function getUserName(): string
    {
        return $this->event->getUserName();
    }

    public function getRoomId(): int
    {
        return $this->event->getRoomId();
    }
}
