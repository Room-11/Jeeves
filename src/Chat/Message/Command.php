<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class Command extends Message
{
    private $commandName;

    private $parameters;

    private $text;

    public function __construct(MessageEvent $event, ChatRoom $room)
    {
        parent::__construct($event, $room);

        $commandParts = preg_split('#\s+#', trim(parent::getText()), -1, PREG_SPLIT_NO_EMPTY);

        $this->commandName = substr(array_shift($commandParts), 2);
        $this->parameters = $commandParts;

        var_dump($this->getText());
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

    public function getText(): string
    {
        if (!isset($this->text)) {
            $this->text = ltrim(substr(parent::getText(), strlen($this->commandName) + 2));
        }

        return $this->text;
    }
}
