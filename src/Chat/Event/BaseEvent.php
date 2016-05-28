<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

abstract class BaseEvent implements Event, \JsonSerializable
{
    const TYPE_ID = 0;

    private $eventId;

    private $timestamp;

    private $data;

    protected function __construct(array $data)
    {
        $this->data = $data;

        $this->eventId   = (int)$data['id'];
        $this->timestamp = new \DateTimeImmutable('@' . ((int)$data['time_stamp']));
    }

    public function getTypeId(): int
    {
        return static::TYPE_ID;
    }

    public function getRawData(): array
    {
        return $this->data;
    }

    public function getId(): int
    {
        return $this->eventId;
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function jsonSerialize()
    {
        return $this->data;
    }

    public function __debugInfo()
    {
        return $this->data;
    }
}
