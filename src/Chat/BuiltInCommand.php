<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat;

use Room11\Jeeves\Chat\Message\Command;

interface BuiltInCommand
{
    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator;

    /**
     * Get a list of specific commands handled by this built-in
     *
     * @return string[]
     */
    public function getCommandNames(): array;
}
