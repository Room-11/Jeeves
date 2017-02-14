<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Room11\Jeeves\Chat\Message\Command;

class PendingMessage
{
    private $text;
    private $originatingCommand;

    public function __construct(string $text, Command $originatingCommand = null)
    {
        $this->text = $text;
        $this->originatingCommand = $originatingCommand;
    }

    public function setText(string $text)
    {
        $this->text = $text;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getOriginatingCommand(): Command
    {
        return $this->originatingCommand;
    }
}
