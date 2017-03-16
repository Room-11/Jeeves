<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat;

use Room11\StackChat\Client\RoomContainer;
use Room11\StackChat\Entities\ChatMessage;
use Room11\StackChat\Room\Room;

class Command implements RoomContainer
{
    private $commandName;
    private $parameters;
    private $text;
    private $originatingMessage;

    public function __construct(Room $room, string $commandName, array $parameters, ChatMessage $originatingMessage = null)
    {
        $this->commandName = $commandName;
        $this->parameters = $parameters;
        $this->originatingMessage = $originatingMessage;
    }

    public function getCommandName(): string
    {
        return $this->commandName;
    }

    /**
     * @param int $skip
     * @return array|\string[]
     */
    public function getParameters(int $skip = 0): array
    {
        return $skip
            ? array_slice($this->parameters, $skip)
            : $this->parameters;
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
            $this->text = ltrim(substr($this->originatingMessage->getText(), strlen($this->commandName) + 2) ?: '');
        }

        return $this->text;
    }

    public function getRoom(): Room
    {
        return $this->getRoom();
    }

    public function getOriginatingMessage(): ?ChatMessage
    {
        return $this->originatingMessage;
    }
}
