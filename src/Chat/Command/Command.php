<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Command;

use Room11\Jeeves\Chat\Command\Message as CommandMessage;
use Room11\Jeeves\Chat\Message\Message as ChatMessage;

class Command implements CommandMessage
{
    private $message;

    public function __construct(ChatMessage $message)
    {
        $this->message = $message;
    }

    public function getOrigin(): int
    {
        return $this->message->getId();
    }

    public function getMessage(): ChatMessage {
        return $this->message;
    }

    public function getCommand(): string
    {
        $commandParts = explode(' ', $this->message->getContent());

        return substr($commandParts[0], 2);
    }

    public function getParameters(): array
    {
        $commandParts = explode(' ', $this->message->getContent());

        array_shift($commandParts);

        return $commandParts;
    }

    public function hasParameter(string $parameter): bool
    {
        return in_array($parameter, $this->getParameters(), true);
    }
}
