<?php

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Command\Command;

trait MessageOnlyPlugin
{
    public function handleCommand(Command $command): \Generator { yield; }

    public function getHandledCommands(): array { return []; }

    public function handlesAllMessages(): bool { return true; }
}
