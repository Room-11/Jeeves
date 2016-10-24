<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Entities;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class PostedMessage
{
    private $room;

    private $id;

    private $timestamp;

    public function __construct(ChatRoom $room, int $id, int $timestamp) {
        $this->room = $room;
        $this->id = $id;
        $this->timestamp = new \DateTimeImmutable("@" . $timestamp);
    }

    public function getRoom(): ChatRoom {
        return $this->room;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getTimestamp(): \DateTimeImmutable {
        return $this->timestamp;
    }
}
