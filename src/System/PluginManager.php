<?php declare(strict_types=1);

namespace Room11\Jeeves\System;

use Amp\Promise;
use Amp\Success;
use Psr\Log\LoggerInterface as Logger;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\Chat\EventFilter\Builder as EventFilterBuilder;
use Room11\Jeeves\Chat\EventFilter\Filter;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\Storage\Plugin as PluginStorage;
use Room11\StackChat\Entities\ChatMessage;
use Room11\StackChat\Event\Event;
use Room11\StackChat\Event\RoomSourcedEvent;
use Room11\StackChat\Message;
use Room11\StackChat\Room\Room as ChatRoom;
use function Amp\all;
use function Amp\resolve;

class PluginManager
{
    private $banStorage;
    private $pluginStorage;
    private $logger;
    private $filterBuilder;
    private $builtInActionManager;

    /**
     * @var Plugin[]
     */
    private $registeredPlugins = [];

    private $roomFilteredEventHandlers = [];
    private $typeFilteredEventHandlers = [];
    private $filteredEventHandlers = [];

    /**
     * @var callable[]
     */
    private $messageHandlers = [];

    /**
     * @var PluginCommandEndpoint[][]
     */
    private $commandMap = [];

    /**
     * @var PluginCommandEndpoint[][]
     */
    private $commandEndpoints = [];

    /**
     * @var bool[][]
     */
    private $enabledPlugins = [];

    /**
     * @param callable $handler
     * @param array ...$args
     * @return Promise
     */
    private function invokeCallbackAsPromise(callable $handler, ...$args): Promise
    {
        $result = $handler(...$args);

        if ($result instanceof \Generator) {
            return resolve($result);
        } else if ($result instanceof Promise) {
            return $result;
        }

        return new Success($result);
    }

    /**
     * @param Event $event
     * @return Promise[]
     */
    private function invokeHandlersForEvent(Event $event): array
    {
        $room = $event instanceof RoomSourcedEvent
            ? $event->getRoom()
            : null;

        $filters = array_merge(
            $this->typeFilteredEventHandlers[$event->getTypeId()] ?? [],
            $room ? $this->roomFilteredEventHandlers[$room->getIdentString()] ?? [] : [],
            $this->filteredEventHandlers
        );

        $promises = [];

        foreach ($filters as list($plugin, $filter)) {
            /** @var Filter $filter */
            if (($room === null || $this->isPluginEnabledForRoom($plugin, $room))
                && ($promise = $filter->executeForEvent($event))) {
                $promises[] = $promise;
            }
        }

        return $promises;
    }

    /**
     * @param Message $message
     * @return Promise[]
     */
    private function invokeHandlersForMessage(Message $message): array
    {
        $promises = [];

        foreach ($this->messageHandlers as $pluginName => $handler) {
            if ($this->isPluginEnabledForRoom($pluginName, $message->getRoom())) {
                $promises[] = $this->invokeCallbackAsPromise($handler, $message); // some callbacks may be synchronous
            }
        }

        return $promises;
    }

    private function invokeHandlerForCommand(Command $command)
    {
        $roomIdent = $command->getRoom()->getIdentString();
        $commandName = strtolower($command->getCommandName());
        $userId = $command->getUserId();
        $userIsBanned = yield $this->banStorage->isBanned($command->getRoom(), $userId);

        if ($userIsBanned) {
            $this->logger->debug(
                "User #{$userId} is banned, ignoring event #{$command->getEvent()->getId()} for plugin command endpoints"
                . " (command: {$commandName})"
            );

            return null;
        }

        /** @var Plugin $plugin */
        /** @var PluginCommandEndpoint $endpoint */
        list($plugin, $endpoint) = $this->commandMap[$roomIdent][$commandName];

        // just a sanity check, shouldn't ever be false but in case something goes horribly wrong
        if (!$this->isPluginEnabledForRoom($plugin, $command->getRoom())) {
            $this->logger->debug(
                "Command {$commandName} still present for {$roomIdent} but plugin {$plugin->getName()}"
                . " is disabled! (endpoint: {$endpoint->getName()})"
            );

            return null;
        }

        return $this->invokeCallbackAsPromise($endpoint->getCallback(), $command);
    }

    /**
     * @param Plugin|string $plugin
     * @return string[]|Plugin[]
     */
    private function resolvePluginFromNameOrObject($plugin): array
    {
        $pluginName = strtolower($plugin instanceof Plugin ? $plugin->getName() : (string)$plugin);

        if (!isset($this->registeredPlugins[$pluginName])) {
            throw new \LogicException("Plugin '{$pluginName}' is not registered");
        }

        return [$pluginName, $this->registeredPlugins[$pluginName]];
    }

    public function __construct(
        BanStorage $banStorage,
        PluginStorage $pluginStorage,
        Logger $logger,
        EventFilterBuilder $filterBuilder,
        BuiltInActionManager $builtInActionManager
    ) {
        $this->banStorage = $banStorage;
        $this->pluginStorage = $pluginStorage;
        $this->logger = $logger;
        $this->filterBuilder = $filterBuilder;
        $this->builtInActionManager = $builtInActionManager;
    }

    public function registerPlugin(Plugin $plugin): void
    {
        $pluginClassName = get_class($plugin);
        $pluginName = strtolower($plugin->getName());

        try {
            $this->logger->debug("Registering plugin '{$pluginName}' ({$pluginClassName})");

            $endpoints = $filters = $roomFilters = $typeFilters = [];

            if (null !== $messageHandler = $plugin->getMessageHandler()) {
                if (!is_callable($messageHandler)) {
                    throw new \LogicException('Message handler must be callable or null');
                }
            }

            foreach ($plugin->getCommandEndpoints() as $i => $endpoint) {
                if (!$endpoint instanceof PluginCommandEndpoint) {
                    throw new \LogicException('Invalid endpoint descriptor at index ' . $i);
                }

                $endpoints[strtolower($endpoint->getName())] = $endpoint;
            }
            $this->logger->debug("Found " . count($endpoints) . " command endpoints for plugin '{$pluginName}'");

            foreach ($plugin->getEventHandlers() as $filterString => $handler) {
                $filter = $this->filterBuilder->build($filterString, $handler);
                $filterKey = $pluginName . '#' . $filterString;

                $rooms = $filter->getRooms();
                $types = $filter->getTypes();

                if (empty($rooms) && empty($types)) {
                    $filters[$filterKey] = [$pluginName, $filter];
                    continue;
                }

                foreach ($rooms as $room) {
                    $roomFilters[$room][$filterKey] = [$pluginName, $filter];
                }

                foreach ($types as $type) {
                    $typeFilters[$type][$filterKey] = [$pluginName, $filter];
                }
            }
            $this->logger->debug(
                "Found " . count($filters) . " unindexable event handlers, "
                . count($roomFilters) . " room-indexed event handlers and "
                . count($typeFilters) . " type-indexed event handlers "
                . "for plugin '{$pluginName}'"
            );
        } catch (\Throwable $e) {
            $this->logger->debug("Registration of plugin '{$pluginName}' failed: {$e}");

            throw new PluginRegistrationFailedException(
                "Registration of plugin '{$pluginName}' ({$pluginClassName}) failed", $plugin, $e
            );
        }

        // if we get here, all the plugin data made sense and we can *actually* register it

        $this->registeredPlugins[$pluginName] = $plugin;

        if ($messageHandler !== null) {
            $this->messageHandlers[$pluginName] = $messageHandler;
        }

        $this->commandEndpoints[$pluginName] = $endpoints;

        foreach ($filters as $key => $handler) {
            $this->filteredEventHandlers[$key] = $handler;
        }
        foreach ($roomFilters as $room => $handlers) {
            foreach ($handlers as $key => $handler) {
                $this->roomFilteredEventHandlers[$room][$key] = $handler;
            }
        }
        foreach ($typeFilters as $type => $handlers) {
            foreach ($handlers as $key => $handler) {
                $this->typeFilteredEventHandlers[$type][$key] = $handler;
            }
        }

        $this->logger->debug("Registered plugin '{$pluginName}' successfully");
    }

    /**
     * @param ChatRoom $room
     * @return array
     */
    public function getMappedCommandsForRoom(ChatRoom $room): array
    {
        $result = [];

        /**
         * @var Plugin $plugin
         * @var PluginCommandEndpoint $endpoint
         */
        foreach ($this->commandMap[$room->getIdentString()] as $command => list($plugin, $endpoint)) {
            $result[$command] = [
                'plugin_name' => $plugin->getName(),
                'endpoint_name' => $endpoint->getName(),
                'endpoint_description' => $endpoint->getDescription() ?? $plugin->getDescription(),
            ];
        }

        return $result;
    }

    /**
     * @param ChatRoom $room
     * @param string $command
     * @return bool
     */
    public function isCommandMappedForRoom(ChatRoom $room, string $command): bool
    {
        return isset($this->commandMap[$room->getIdentString()][strtolower($command)]);
    }

    /**
     * @param ChatRoom $room
     * @param Plugin|string $plugin
     * @param string $endpoint
     * @param string $command
     * @return Promise
     */
    public function mapCommandForRoom(ChatRoom $room, $plugin, string $endpoint, string $command): Promise
    {
        $command = strtolower($command);

        if ($this->builtInActionManager->hasRegisteredCommand($command)) {
            throw new \LogicException("Command '{$command}' is built in");
        }

        list($pluginName, $plugin) = $this->resolvePluginFromNameOrObject($plugin);

        return resolve(function() use($room, $pluginName, $plugin, $endpoint, $command) {
            if (!yield $this->pluginStorage->isPluginEnabled($room, $plugin->getName())) {
                throw new \LogicException("Plugin '{$pluginName}' is not enabled for room");
            }

            $endpointName = strtolower($endpoint);
            if (!isset($this->commandEndpoints[$pluginName][$endpointName])) {
                throw new \LogicException("Endpoint '{$endpoint}' not found for plugin '{$pluginName}'");
            }
            $endpoint = $this->commandEndpoints[$pluginName][$endpointName];

            $roomId = $room->getIdentString();
            if (isset($this->commandMap[$roomId][$command])) {
                throw new \LogicException("Command '{$command}' already mapped in room '{$roomId}'");
            }

            $this->commandMap[$roomId][$command] = [$plugin, $endpoint];

            return $this->pluginStorage->addCommandMapping($room, $pluginName, $command, $endpointName);
        });
    }

    /**
     * @param ChatRoom $room
     * @param string $command
     * @return Promise
     */
    public function unmapCommandForRoom($room, string $command): Promise
    {
        $command = strtolower($command);

        if ($this->builtInActionManager->hasRegisteredCommand($command)) {
            throw new \LogicException("Command '{$command}' is built in");
        }

        $roomId = $room->getIdentString();

        if (!isset($this->commandMap[$roomId][$command])) {
            throw new \LogicException("Command '{$command}' not mapped in room '{$roomId}'");
        }

        list($pluginName) = $this->resolvePluginFromNameOrObject($this->commandMap[$roomId][$command][0]);

        unset($this->commandMap[$roomId][$command]);

        return $this->pluginStorage->removeCommandMapping($room, $pluginName, $command);
    }

    /**
     * @param Plugin|string $plugin
     * @param ChatRoom $room
     * @return bool
     */
    public function isPluginEnabledForRoom($plugin, ChatRoom $room): bool
    {
        list($pluginName) = $this->resolvePluginFromNameOrObject($plugin);

        return isset($this->enabledPlugins[$room->getIdentString()][$pluginName]);
    }

    /**
     * @param ChatRoom $room
     * @param bool $persist
     * @return Promise
     */
    public function enableAllPluginsForRoom(ChatRoom $room, bool $persist = false): Promise
    {
        return resolve(function () use ($room, $persist) {
            $promises = [];

            foreach ($this->registeredPlugins as $plugin) {
                if ($persist || yield $this->pluginStorage->isPluginEnabled($room, $plugin->getName())) {
                    $promises[] = $this->enablePluginForRoom($plugin, $room, $persist);
                }
            }

            yield all($promises);
        });
    }

    /**
     * @param ChatRoom $room
     * @param bool $persist
     * @return Promise
     */
    public function disableAllPluginsForRoom(ChatRoom $room, bool $persist = false): Promise
    {
        return all(array_map(function(Plugin $plugin) use($room, $persist) {
            return $this->disablePluginForRoom($plugin, $room, $persist);
        }, $this->registeredPlugins));
    }

    /**
     * @param Plugin|string $plugin
     * @param ChatRoom $room
     * @param bool $persist
     * @return Promise
     */
    public function disablePluginForRoom($plugin, ChatRoom $room, bool $persist = true): Promise
    {
        list($pluginName, $plugin) = $this->resolvePluginFromNameOrObject($plugin);
        $roomId = $room->getIdentString();

        $yesNo = $persist ? 'yes' : 'no';
        $this->logger->debug("Disabling plugin '{$pluginName}' for room '{$roomId}' (persist = {$yesNo})");

        unset($this->enabledPlugins[$roomId][$pluginName]);

        foreach ($this->commandMap[$roomId] as $command => list($cmdPlugin, $cmdEndpoint)) {
            if ($cmdPlugin === $plugin) {
                unset($this->commandMap[$roomId][$command]);
            }
        }

        return resolve(function() use($plugin, $pluginName, $room, $persist) {
            try {
                yield $this->invokeCallbackAsPromise([$plugin, 'disableForRoom'], $room, $persist);
            } catch (\Throwable $e) {
                $this->logger->debug("Unhandled exception in " . get_class($plugin) . '#disableForRoom(): ' . $e);
            }

            if ($persist) {
                yield $this->pluginStorage->setPluginEnabled($room, $pluginName, false);
            }
        });
    }

    /**
     * @param Plugin|string $plugin
     * @param ChatRoom $room
     * @param bool $persist
     * @return Promise
     */
    public function enablePluginForRoom($plugin, ChatRoom $room, bool $persist = true): Promise
    {
        return resolve(function() use($plugin, $room, $persist) {
            list($pluginName, $plugin) = $this->resolvePluginFromNameOrObject($plugin);
            $roomId = $room->getIdentString();

            $yesNo = $persist ? 'yes' : 'no';
            $this->logger->debug("Enabling plugin '{$pluginName}' for room '{$roomId}' (persist = {$yesNo})");

            try {
                yield $this->invokeCallbackAsPromise([$plugin, 'enableForRoom'], $room, $persist);
            } catch (\Throwable $e) {
                $this->logger->debug("Unhandled exception in " . get_class($plugin) . '#enableForRoom(): ' . $e);
            }

            $this->enabledPlugins[$roomId][$pluginName] = true;

            $commandMappings = yield $this->pluginStorage->getAllMappedCommands($room, $pluginName);

            if ($commandMappings === null) {
                foreach ($this->commandEndpoints[$pluginName] as $endpoint) {
                    if (null !== $command = $endpoint->getDefaultCommand()) {
                        $this->commandMap[$roomId][$command] = [$plugin, $endpoint];
                        yield $this->pluginStorage->addCommandMapping($room, $pluginName, $command, strtolower($endpoint->getName()));
                    }
                }
            } else {
                foreach ($commandMappings as $command => $endpointName) {
                    if (isset($this->commandEndpoints[$pluginName][$endpointName])) {
                        $endpoint = $this->commandEndpoints[$pluginName][$endpointName];
                        $this->commandMap[$roomId][$command] = [$plugin, $endpoint];
                    }
                }
            }

            if ($persist) {
                yield $this->pluginStorage->setPluginEnabled($room, $pluginName, true);
            }
        });
    }

    /**
     * @return Plugin[]
     */
    public function getRegisteredPlugins(): array
    {
        return $this->registeredPlugins;
    }

    /**
     * @param Plugin|string $plugin
     * @return bool
     */
    public function isPluginRegistered($plugin): bool
    {
        $name = strtolower($plugin instanceof Plugin ? $plugin->getName() : (string)$plugin);
        return isset($this->registeredPlugins[$name]);
    }

    public function getPluginByName(string $name): Plugin
    {
        if (!isset($this->registeredPlugins[$name = strtolower($name)])) {
            throw new \LogicException("Cannot get unknown plugin {$name}");
        }

        return $this->registeredPlugins[$name];
    }

    /**
     * @param $plugin
     * @param ChatRoom $room
     * @return array
     */
    public function getPluginCommandEndpoints($plugin, ChatRoom $room = null): array
    {
        /** @var Plugin $plugin */
        list($pluginName, $plugin) = $this->resolvePluginFromNameOrObject($plugin);

        $roomId = null;
        if ($room !== null) {
            $roomId = $room->getIdentString();
        }

        $endpoints = [];

        foreach ($this->commandEndpoints[$pluginName] ?? [] as $endpoint) {
            $endpointData = [
                'description'     => $endpoint->getDescription() ?? $plugin->getDescription(),
                'default_command' => $endpoint->getDefaultCommand(),
                'mapped_commands' => [],
            ];

            if ($roomId !== null) {
                foreach ($this->commandMap[$roomId] ?? [] as $command => list($mappedPlugin, $mappedEndpoint)) {
                    if ($endpoint === $mappedEndpoint) {
                        $endpointData['mapped_commands'][] = $command;
                    }
                }
            }

            $endpoints[$endpoint->getName()] = $endpointData;
        }

        return $endpoints;
    }

    public function handleCommand(Command $command): Promise
    {
        $roomIdent = $command->getRoom()->getIdentString();
        $commandName = strtolower($command->getCommandName());

        if (!isset($this->commandMap[$roomIdent][$commandName])) {
            return new Success();
        }

        return resolve($this->invokeHandlerForCommand($command));
    }

    public function handleMessage(ChatMessage $message): Promise
    {
        return all($this->invokeHandlersForMessage($message));
    }

    public function handleEvent(Event $event): Promise
    {
        return all($this->invokeHandlersForEvent($event));
    }
}
