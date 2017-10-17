<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat;

use Ds\Deque;

class FixedSizeEventBuffer
{
    private $size;
    private $buffer;

    public function __construct(int $size)
    {
        $this->buffer = new Deque();
        $this->setSize($size);
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
        $this->buffer->allocate($size);
    }

    public function contains(int $eventId): bool
    {
        return $this->buffer->contains($eventId);
    }

    public function push(int $eventId): void
    {
        $this->buffer->push($eventId);

        if ($this->buffer->count() > $this->size) {
            $this->buffer->shift();
        }
    }
}
