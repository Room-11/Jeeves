<?php declare(strict_types=1);

namespace Room11\Jeeves\System;

use Amp\Promise;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;

interface BuiltInCommand
{
    /**
     * Handle a command message
     *
     * @param CommandMessage $command
     * @return Promise
     */
    function handleCommand(CommandMessage $command): Promise;

    /**
     * Get a list of specific commands handled by this built-in
     *
     * @return BuiltInCommandInfo[]
     */
    function getCommandInfo(): array;
}
