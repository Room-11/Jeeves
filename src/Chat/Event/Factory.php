<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

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
     * @return Event[]
     */
    public function build(array $data): array
    {
        $message = reset($data);
        $result = [];

        foreach ($message['e'] ?? [] as $event) {
            $eventType = (int)($event['event_type'] ?? 0);

            $result[] = isset($this->classes[$eventType])
                ? new $this->classes[$eventType]($event)
                : new Unknown($data);
        }

        return $result;
    }
}
