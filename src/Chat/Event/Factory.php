<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Chat\WebSocket\Handler as WebSocketHandler;

class Factory
{
    /**
     * @var string[]
     */
    private $classes = [
        Unknown::TYPE_ID => Unknown::class,
        NewMessage::TYPE_ID => NewMessage::class,
        EditMessage::TYPE_ID => EditMessage::class,
        UserEnter::TYPE_ID => UserEnter::class,
        UserLeave::TYPE_ID => UserLeave::class,
        RoomEdit::TYPE_ID => RoomEdit::class,
        StarMessage::TYPE_ID => StarMessage::class,
        Mention::TYPE_ID => Mention::class,
        DeleteMessage::TYPE_ID => DeleteMessage::class,
        Invitation::TYPE_ID => Invitation::class,
        ReplyMessage::TYPE_ID => ReplyMessage::class,
    ];

    public function build(int $eventType, array $data, ChatRoom $room): Event
    {
        return isset($this->classes[$eventType])
            ? new $this->classes[$eventType]($data, $room)
            : new Unknown($data);
    }
}
