<?php

namespace Room11\Jeeves\Chat;

use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Storage\Ban as BanStorage;

class BuiltInCommandManager
{
    private $banStorage;

    /**
     * @var BuiltInCommand[][]
     */
    private $commands = [];

    public function __construct(BanStorage $banStorage)
    {
        $this->banStorage = $banStorage;
    }

    public function register(BuiltInCommand $command): BuiltInCommandManager
    {
        foreach ($command->getCommandNames() as $commandName) {
            $this->commands[$commandName][] = $command;
        }

        return $this;
    }

    public function handle(Command $command): \Generator
    {
        if (yield from $this->banStorage->isBanned($command->getUserId())) {
            return;
        }

        if (isset($this->commands[$command->getCommandName()])) {
            foreach ($this->commands[$command->getCommandName()] as $plugin) {
                yield from $plugin->handleCommand($command);
            }
        }
    }
}
