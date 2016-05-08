<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class Factory
{
    /**
     * @var string[]
     */
    private $classes = [
        Unknown::EVENT_TYPE_ID => Unknown::class,
        NewMessage::EVENT_TYPE_ID => NewMessage::class,
        EditMessage::EVENT_TYPE_ID => EditMessage::class,
        UserEnter::EVENT_TYPE_ID => UserEnter::class,
        UserLeave::EVENT_TYPE_ID => UserLeave::class,
        RoomEdit::EVENT_TYPE_ID => RoomEdit::class,
        StarMessage::EVENT_TYPE_ID => StarMessage::class,
        MentionMessage::EVENT_TYPE_ID => MentionMessage::class,
        DeleteMessage::EVENT_TYPE_ID => DeleteMessage::class,
    ];

    /**
     * @param array $data
     * @param ChatRoom $room
     * @return array|Event[]
     */
    public function build(array $data, ChatRoom $room): array
    {
        $result = [];

        foreach ($data['r' . $room->getIdentifier()->getId()]['e'] ?? [] as $eventData) {
            if (!isset($eventData['id'])) {
                continue;
            }

            $eventId = (int)$eventData['id'];
            if (isset($result[$eventId])) {
                continue;
            }

            $eventType = (int)($eventData['event_type'] ?? 0);

            $event = isset($this->classes[$eventType])
                ? new $this->classes[$eventType]($eventData, $room)
                : new Unknown($eventData);

            if ($event instanceof RoomSourcedEvent && $eventData['room_id'] !== $room->getIdentifier()->getId()) {
                continue;
            }

            $result[$eventId] = $event;
        }

        return $result;
    }
}
