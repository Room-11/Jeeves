<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\MessageEvent;

class Command extends Message
{
    private $commandName;

    private $parameters;

    public function __construct(MessageEvent $event)
    {
        parent::__construct($event);

        $commandParts = preg_split('#\s+#', trim($event->getMessageContent()), -1, PREG_SPLIT_NO_EMPTY);

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

    public function hasParameter(string $parameter): bool
    {
        return in_array($parameter, $this->parameters, true);
    }

    public function hasParameters(): bool
    {
        return !empty($this->parameters);
    }
}
