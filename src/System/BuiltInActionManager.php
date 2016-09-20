<?php declare(strict_types=1);

namespace Room11\Jeeves\System;

use Amp\Promise;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Storage\Ban as BanStorage;
use function Amp\resolve;

class BuiltInActionManager
{
    private $banStorage;
    private $logger;

    /**
     * @var BuiltInCommand[]
     */
    private $commands = [];

    public function __construct(BanStorage $banStorage, Logger $logger)
    {
        $this->banStorage = $banStorage;
        $this->logger = $logger;
    }

    public function register(BuiltInCommand $command): BuiltInActionManager
    {
        $className = get_class($command);

        foreach ($command->getCommandNames() as $commandName) {
            $this->logger->log(Level::DEBUG, "Registering command name '{$commandName}' with built in command {$className}");
            $this->commands[$commandName] = $command;
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function getRegisteredCommands(): array
    {
        return array_keys($this->commands);
    }

    public function handleCommand(Command $command): Promise
    {
        return resolve(function() use($command) {
            $commandName = $command->getCommandName();
            if (!isset($this->commands[$commandName])) {
                return;
            }

            $eventId = $command->getEvent()->getId();

            $userId = $command->getUserId();
            $userIsBanned = yield $this->banStorage->isBanned($command->getRoom(), $userId);

            if ($userIsBanned) {
                $this->logger->log(Level::DEBUG, "User #{$userId} is banned, ignoring event #{$eventId} for built in commands");
                return;
            }

            $this->logger->log(Level::DEBUG, "Passing event #{$eventId} to built in command handler " . get_class($this->commands[$commandName]));
            yield $this->commands[$commandName]->handleCommand($command);
        });
    }
}
