<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

interface UserMessage {
    public function getUserId(): int;
}
