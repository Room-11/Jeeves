<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Entities;

use Room11\Jeeves\Chat\Client\IdentifiableMessage;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class PostedMessage implements IdentifiableMessage
{
    private $room;
    private $id;
    private $timestamp;
    private $text;
    private $originatingCommand;

    public function __construct(ChatRoom $room, int $id, int $timestamp, string $text, ?Command $originatingCommand)
    {
        $this->room = $room;
        $this->id = $id;
        $this->timestamp = new \DateTimeImmutable("@{$timestamp}");
        $this->text = $text;
        $this->originatingCommand = $originatingCommand;
    }

    public function getRoom(): ChatRoom
    {
        return $this->room;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getOriginatingCommand(): ?Command
    {
        return $this->originatingCommand;
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }
}
