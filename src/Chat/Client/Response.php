<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

class Response {
    private $messageId;

    private $timestamp;

    public function __construct(int $messageId, int $timestamp) {
        $this->messageId = $messageId;
        $this->timestamp = new \DateTimeImmutable("@" . $timestamp);
    }

    public function getMessageId(): int {
        return $this->messageId;
    }

    public function getTimestamp(): \DateTimeImmutable {
        return $this->timestamp;
    }
}
