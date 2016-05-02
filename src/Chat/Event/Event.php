<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

abstract class Event
{
    const EVENT_TYPE_ID = 0;

    private $eventId;

    private $timestamp;

    protected function __construct(int $eventId, int $timestamp)
    {
        $this->eventId   = $eventId;
        $this->timestamp = new \DateTime('@' . $timestamp);
    }

    public function getEventTypeId(): int
    {
        return static::EVENT_TYPE_ID;
    }

    public function getEventId(): int
    {
        return $this->eventId;
    }

    public function getTimestamp(): \DateTime
    {
        return $this->timestamp;
    }
}
