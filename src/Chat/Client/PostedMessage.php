<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class PostedMessage {
    private $room;

    private $messageId;

    private $timestamp;

    public function __construct(ChatRoom $room, int $messageId, int $timestamp) {
        $this->room = $room;
        $this->messageId = $messageId;
        $this->timestamp = new \DateTimeImmutable("@" . $timestamp);
    }

    public function getRoom(): ChatRoom {
        return $this->room;
    }

    public function getMessageId(): int {
        return $this->messageId;
    }

    public function getTimestamp(): \DateTimeImmutable {
        return $this->timestamp;
    }
}
