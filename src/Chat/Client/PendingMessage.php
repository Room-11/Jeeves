<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

class PendingMessage
{
    private $message;
    private $commandId = null;

    public function __construct(string $message, ?int $commandId)
    {
        $this->setMessage($message);

        if (!is_null($commandId)) {
            $this->setCommandId((int) $commandId);
        }
    }

    public function setMessage(string $message)
    {
        $this->message = $message;
    }

    public function setCommandId(int $commandId)
    {
        $this->commandId = $commandId;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getCommandId()
    {
        return $this->commandId;
    }
}
