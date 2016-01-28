<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Command;

use Room11\Jeeves\Chat\Command\Message as CommandMessage;
use Room11\Jeeves\Chat\Message\Message;

class Void implements CommandMessage
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

    public function getMessage(): Message {
        return $this->message;
    }
}
