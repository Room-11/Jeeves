<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;

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
        MentionMessage::TYPE_ID => MentionMessage::class,
        DeleteMessage::TYPE_ID => DeleteMessage::class,
        ReplyMessage::TYPE_ID => ReplyMessage::class,
    ];

    /**
     * @param int $eventType
     * @param array $data
     * @param ChatRoom $room
     * @return Event
     */
    public function build(int $eventType, array $data, ChatRoom $room): Event
    {
        return isset($this->classes[$eventType])
            ? new $this->classes[$eventType]($data, $room)
            : new Unknown($data);
    }
}
