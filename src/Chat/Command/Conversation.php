<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Command;

use Room11\Jeeves\Chat\Command\Message as CommandMessage;
use Room11\Jeeves\Chat\Message\Message;

class Conversation implements CommandMessage
{
    private $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function getOrigin(): int
    {
        return $this->message->getId();
    }

    public function getText(): string
    {
        return $this->message->getContent();
    }
}
