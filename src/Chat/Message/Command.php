<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class Command extends Message
{
    private $commandName;

    private $parameters;

    public function __construct(MessageEvent $event, ChatRoom $room)
    {
        parent::__construct($event, $room);

        $commandParts = preg_split('#\s+#', trim($this->getText()), -1, PREG_SPLIT_NO_EMPTY);

        $this->commandName = substr(array_shift($commandParts), 2);
        $this->parameters = $commandParts;
    }

    public function getCommandName(): string
    {
        return $this->commandName;
    }

    /**
     * @return string[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param int $index
     * @return string
     */
    public function getParameter(int $index)
    {
        return $this->parameters[$index] ?? null;
    }

    public function hasParameter(string $parameter): bool
    {
        return in_array($parameter, $this->parameters, true);
    }

    public function hasParameters(int $minCount = -1): bool
    {
        return !empty($this->parameters) && ($minCount < 0 || count($this->parameters) >= $minCount);
    }
}
