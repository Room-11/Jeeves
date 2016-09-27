<?php declare(strict_types=1);

namespace Room11\Jeeves\System;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Event\Event;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Storage\Ban as BanStorage;
use function Amp\all;
use function Amp\resolve;

class BuiltInActionManager
{
    private $banStorage;
    private $logger;

    /**
     * @var BuiltInCommand[]
     */
    private $commands = [];

    /**
     * @var BuiltInEventHandler[][]
     */
    private $eventHandlers = [];

    public function __construct(BanStorage $banStorage, Logger $logger)
    {
        $this->banStorage = $banStorage;
        $this->logger = $logger;
    }

    public function registerCommand(BuiltInCommand $command): BuiltInActionManager
    {
        $className = get_class($command);

        foreach ($command->getCommandNames() as $commandName) {
            $this->logger->log(Level::DEBUG, "Registering command name '{$commandName}' with built in command {$className}");
            $this->commands[$commandName] = $command;
        }

        return $this;
    }

    public function registerEventHandler(BuiltInEventHandler $handler)
    {
        $className = get_class($handler);

        foreach ($handler->getEventTypes() as $eventType) {
            $this->logger->log(Level::DEBUG, "Registering event type {$eventType} with built in handler {$className}");
            $this->eventHandlers[$eventType][] = $handler;
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

    public function handleEvent(Event $event): Promise
    {
        if (!isset($this->eventHandlers[$event->getTypeId()])) {
            return new Success();
        }

        return all(array_map(function(BuiltInEventHandler $handler) use($event) {
            return $handler->handleEvent($event);
        }, $this->eventHandlers[$event->getTypeId()]));
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
