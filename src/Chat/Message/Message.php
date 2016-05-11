<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\DeleteMessage;
use Room11\Jeeves\Chat\Event\EditMessage;
use Room11\Jeeves\Chat\Event\MentionMessage;
use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Event\NewMessage;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class Message
{
    const TYPE_NEW = 1;
    const TYPE_EDIT = 2;
    const TYPE_DELETE = 3;

    private static $eventTypeMap = [
        NewMessage::TYPE_ID => self::TYPE_NEW,
        DeleteMessage::TYPE_ID => self::TYPE_DELETE,
        EditMessage::TYPE_ID => self::TYPE_EDIT,
        MentionMessage::TYPE_ID => self::TYPE_NEW,
    ];

    private $event;

    private $room;

    private $type;

    public function __construct(MessageEvent $event, ChatRoom $room)
    {
        $this->event = $event;
        $this->room = $room;
        $this->type = self::$eventTypeMap[$event->getTypeId()];
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

    public function getType(): int
    {
        return $this->type;
    }
}
