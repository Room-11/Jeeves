<?php declare(strict_types=1);

namespace Room11\Jeeves\System;

use Amp\Promise;
use Amp\Success;
use Psr\Log\LoggerInterface as Logger;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\Chat\RoomStatusManager;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\StackChat\Event\Event;
use function Amp\all;
use function Amp\resolve;

class BuiltInActionManager
{
    private $banStorage;
    private $roomStatusManager;
    private $logger;

    /**
     * @var BuiltInCommand[]
     */
    private $commands = [];

    /**
     * @var BuiltInCommandInfo[]
     */
    private $commandInfo = [];

    /**
     * @var BuiltInEventHandler[][]
     */
    private $eventHandlers = [];

    public function __construct(BanStorage $banStorage, RoomStatusManager $roomStatusManager, Logger $logger)
    {
        $this->banStorage = $banStorage;
        $this->roomStatusManager = $roomStatusManager;
        $this->logger = $logger;
    }

    public function registerCommand(BuiltInCommand $command): BuiltInActionManager
    {
        $className = get_class($command);

        foreach ($command->getCommandInfo() as $commandInfo) {
            $commandName = strtolower($commandInfo->getCommand());

            $this->commands[$commandName] = $command;
            $this->commandInfo[$commandName] = $commandInfo;

            $this->logger->debug("Registered command name '{$commandName}' with built in command {$className}");
        }

        ksort($this->commandInfo);

        return $this;
    }

    public function registerEventHandler(BuiltInEventHandler $handler)
    {
        $className = get_class($handler);

        foreach ($handler->getEventTypes() as $eventType) {
            $this->logger->debug("Registering event type {$eventType} with built in handler {$className}");
            $this->eventHandlers[$eventType][] = $handler;
        }

        return $this;
    }

    public function hasRegisteredCommand(string $command): bool
    {
        return isset($this->commands[strtolower($command)]);
    }

    /**
     * @return BuiltInCommandInfo[]
     */
    public function getRegisteredCommandInfo(): array
    {
        return $this->commandInfo;
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
            $commandName = strtolower($command->getCommandName());

            if (!isset($this->commands[$commandName])) {
                return;
            }

            $room = $command->getRoom();

            if ($this->commandInfo[$commandName]->requiresApprovedRoom() && !yield $this->roomStatusManager->isApproved($room)) {
                return;
            }

            $eventId = $command->getEvent()->getId();

            $userId = $command->getUserId();

            try {
                $userIsBanned = yield $this->banStorage->isBanned($room, $userId);

                if ($userIsBanned) {
                    $this->logger->debug("User #{$userId} is banned, ignoring event #{$eventId} for built in commands");
                    return;
                }

                $this->logger->debug("Passing event #{$eventId} to built in command handler " . get_class($this->commands[$commandName]));
                yield $this->commands[$commandName]->handleCommand($command);
            } catch (\Throwable $e) {
                $this->logger->error("Something went wrong while handling #{$eventId} for built-in commands: {$e}");
            }
        });
    }
}
