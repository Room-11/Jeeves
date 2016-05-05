<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class Message
{
    private $event;

    private $room;

    public function __construct(MessageEvent $event, ChatRoom $room)
    {
        $this->event = $event;
        $this->room = $room;
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

    public function getRoom(): ChatRoom
    {
        return $this->room;
    }
}
