<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat;

use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Storage\Ban as BanStorage;

class BuiltInCommandManager
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

    public function register(BuiltInCommand $command): BuiltInCommandManager
    {
        $className = get_class($command);

        foreach ($command->getCommandNames() as $commandName) {
            $this->logger->log(Level::DEBUG, "Registering command name '{$commandName}' with built in command {$className}");
            $this->commands[$commandName] = $command;
        }

        return $this;
    }

    public function handle(Command $command): \Generator
    {
        $eventId = $command->getEvent()->getEventId();
        $userId = $command->getUserId();

        $this->logger->log(Level::DEBUG, "Processing event #{$eventId} for built in commands");

        if (yield from $this->banStorage->isBanned($command->getRoom(), $userId)) {
            $this->logger->log(Level::DEBUG, "User #{$userId} is banned, ignoring event #{$eventId} for built in commands");
            return;
        }

        $commandName = $command->getCommandName();
        if (isset($this->commands[$commandName])) {
            $this->logger->log(Level::DEBUG, "Passing event #{$eventId} to built in command handler " . get_class($this->commands[$commandName]));
            yield from $this->commands[$commandName]->handleCommand($command);
        }

        $this->logger->log(Level::DEBUG, "Event #{$eventId} processed for built in commands");
    }
}
