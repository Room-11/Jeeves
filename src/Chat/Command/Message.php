<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Command;

use Room11\Jeeves\Chat\Message\Message as ChatMessage;

interface Message {
    public function getOrigin(): int;
    public function getMessage(): ChatMessage;
}
