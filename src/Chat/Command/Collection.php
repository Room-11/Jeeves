<?php

namespace Room11\Jeeves\Chat\Command;

use Room11\Jeeves\Chat\Message\Message;

class Collection implements Command
{
    private $commands = [];

    public function register(Command $command): Collection
    {
        $this->commands[] = $command;

        return $this;
    }

    public function handle(Message $message): \Generator
    {
        foreach ($this->commands as $command) {
            yield from $command->handle($message);
        }
    }
}
