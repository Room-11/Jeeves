<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Entities;

use Room11\Jeeves\Chat\Client\IdentifiableMessage;
use Room11\Jeeves\Chat\Client\PendingMessage;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class PostedMessage implements IdentifiableMessage
{
    private $room;
    private $id;
    private $timestamp;
    private $message;

    public function __construct(ChatRoom $room, int $id, int $timestamp, PendingMessage $message)
    {
        $this->room = $room;
        $this->id = $id;
        $this->timestamp = new \DateTimeImmutable("@{$timestamp}");
        $this->message = $message;
    }

    public function getRoom(): ChatRoom
    {
        return $this->room;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMessage(): PendingMessage
    {
        return $this->message;
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }
}
