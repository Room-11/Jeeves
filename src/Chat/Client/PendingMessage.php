<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

class PendingMessage
{
    private $text;
    private $commandMessageId = null;

    public function __construct(string $message, ?int $commandId = null)
    {
        $this->setText($message);

        if (!is_null($commandId)) {
            $this->setCommandMessageId((int)$commandId);
        }
    }

    public function setText(string $text)
    {
        $this->text = $text;
    }

    public function getText()
    {
        return $this->text;
    }

    public function setCommandMessageId(int $commandMessageId)
    {
        $this->commandMessageId = $commandMessageId;
    }

    public function getCommandMessageId()
    {
        return $this->commandMessageId;
    }
}
