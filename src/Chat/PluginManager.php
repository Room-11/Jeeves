<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat;

use Amp\Promise;
use Room11\Jeeves\Chat\Event\Event;
use Room11\Jeeves\Chat\Event\Filter\Builder as EventFilterBuilder;
use Room11\Jeeves\Chat\Event\Filter\Filter;
use Room11\Jeeves\Chat\Event\RoomSourcedEvent;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Message\Message;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Storage\Ban as BanStorage;
use function Amp\all;
use function Amp\resolve;

class PluginManager
{
    private $banStorage;
    private $logger;
    private $filterBuilder;

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

    private $enabledPlugins = [];

    /**
     * @param callable $handler
     * @param array ...$args
     * @return Promise|null
     */
    private function invokeCallbackAsPromise(callable $handler, ...$args)
    {
        $result = $handler(...$args);

        if ($result instanceof \Generator) {
            return resolve($result);
        } else if ($handler instanceof Promise) {
            return $result;
        }

        return null;
    }

    /**
     * @param Event $event
     * @return Promise[]
     */
    private function invokeHandlersForEvent(Event $event): array
    {
        $room = $event instanceof RoomSourcedEvent
            ? $event->getRoom()->getIdentifier()->getIdentString()
            : null;

        $filters = array_merge(
            $this->typeFilteredEventHandlers[$event->getTypeId()] ?? [],
            $this->roomFilteredEventHandlers[$room] ?? [],
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

    private function resolveRoomFromIdentOrObject($room): string
    {
        if ($room instanceof ChatRoom) {
            $room = $room->getIdentifier();
        }

        return $room instanceof ChatRoomIdentifier ? $room->getIdentString() : (string)$room;
    }

    public function __construct(BanStorage $banStorage, Logger $logger, EventFilterBuilder $filterBuilder)
    {
        $this->banStorage = $banStorage;
        $this->logger = $logger;
        $this->filterBuilder = $filterBuilder;
    }

    public function registerPlugin(Plugin $plugin) /*: void*/
    {
        $pluginClassName = get_class($plugin);
        $pluginName = strtolower($plugin->getName());

        try {
            $this->logger->log(Level::DEBUG, "Registering plugin '{$pluginName}' ({$pluginClassName})");

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
            $this->logger->log(Level::DEBUG, "Found " . count($endpoints) . " command endpoints for plugin '{$pluginName}'");

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
            $this->logger->log(
                Level::DEBUG,
                "Found " . count($filters) . " unindexable event handlers, "
                . count($roomFilters) . " room-indexed event handlers and "
                . count($typeFilters) . " type-indexed event handlers "
                . "for plugin '{$pluginName}'"
            );
        } catch (\Throwable $e) {
            $this->logger->log(Level::DEBUG, "Registration of plugin '{$pluginName}' failed", $e);

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

        foreach ($filters as $handler) {
            $this->filteredEventHandlers[] = $handler;
        }
        foreach ($roomFilters as $room => $handlers) {
            foreach ($handlers as $handler) {
                $this->roomFilteredEventHandlers[$room][] = $handler;
            }
        }
        foreach ($typeFilters as $type => $handlers) {
            foreach ($handlers as $handler) {
                $this->typeFilteredEventHandlers[$type][] = $handler;
            }
        }

        $this->logger->log(Level::DEBUG, "Registered plugin '{$pluginName}' successfully");
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @return array
     */
    public function getMappedCommandsForRoom($room): array
    {
        $roomId = $this->resolveRoomFromIdentOrObject($room);

        $result = [];

        /**
         * @var Plugin $plugin
         * @var PluginCommandEndpoint $endpoint
         */
        foreach ($this->commandMap[$roomId] as $command => list($plugin, $endpoint)) {
            $result[$command] = [
                'plugin_name' => $plugin->getName(),
                'endpoint_name' => $endpoint->getName(),
                'endpoint_description' => $endpoint->getDescription() ?? $plugin->getDescription(),
            ];
        }

        return $result;
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @param string $command
     * @return bool
     */
    public function isCommandMappedForRoom($room, string $command): bool
    {
        return isset($this->commandMap[$this->resolveRoomFromIdentOrObject($room)][$command]);
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @param Plugin|string $plugin
     * @param string $endpoint
     * @param string $command
     * @todo persist this
     */
    public function mapCommandForRoom($room, $plugin, string $endpoint, string $command) /*: void*/
    {
        list($pluginName, $plugin) = $this->resolvePluginFromNameOrObject($plugin);

        if (!isset($this->commandEndpoints[$pluginName][strtolower($endpoint)])) {
            throw new \LogicException("Endpoint '{$endpoint}' not found for plugin '{$pluginName}'");
        }

        $endpoint = $this->commandEndpoints[$pluginName][strtolower($endpoint)];
        $roomId = $this->resolveRoomFromIdentOrObject($room);

        $this->commandMap[$roomId][$command] = [$plugin, $endpoint];
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @param string $command
     * @todo persist this
     */
    public function unmapCommandForRoom($room, string $command) /*: void*/
    {
        $roomId = $this->resolveRoomFromIdentOrObject($room);

        if (!isset($this->commandMap[$roomId][$command])) {
            throw new \LogicException("Command '{$command}' not mapped in room '{$roomId}'");
        }

        unset($this->commandMap[$roomId][$command]);
    }

    /**
     * @param Plugin|string $plugin
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @return bool
     */
    public function isPluginEnabledForRoom($plugin, $room): bool
    {
        list($pluginName) = $this->resolvePluginFromNameOrObject($plugin);
        $roomId = $this->resolveRoomFromIdentOrObject($room);

        return isset($this->enabledPlugins[$roomId][$pluginName]);
    }

    /**
     * @param Plugin|string $plugin
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @todo persist this
     */
    public function disablePluginForRoom($plugin, $room) /*: void*/
    {
        list($pluginName, $plugin) = $this->resolvePluginFromNameOrObject($plugin);
        $roomId = $this->resolveRoomFromIdentOrObject($room);

        $this->logger->log(Level::DEBUG, "Disabling plugin '{$pluginName}' for room '{$roomId}'");

        unset($this->enabledPlugins[$roomId][$pluginName]);

        foreach ($this->commandEndpoints[$pluginName] as $endpoint) {
            if (null !== $command = $endpoint->getDefaultCommand()) {
                unset($this->commandMap[$roomId][$command]);
            }
        }

        $plugin->disableForRoom($roomId);
    }

    /**
     * @param Plugin|string $plugin
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @todo persist this
     */
    public function enablePluginForRoom($plugin, $room) /*: void*/
    {
        list($pluginName, $plugin) = $this->resolvePluginFromNameOrObject($plugin);
        $roomId = $this->resolveRoomFromIdentOrObject($room);

        $this->logger->log(Level::DEBUG, "Enabling plugin '{$pluginName}' for room '{$roomId}'");
        $this->enabledPlugins[$roomId][$pluginName] = true;

        foreach ($this->commandEndpoints[$pluginName] as $endpoint) {
            if (null !== $command = $endpoint->getDefaultCommand()) {
                $this->commandMap[$roomId][$command] = [$plugin, $endpoint];
            }
        }

        $plugin->disableForRoom($roomId);
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

    public function getPluginCommandEndpoints($plugin, $room = null): array
    {
        /** @var Plugin $plugin */
        list($pluginName, $plugin) = $this->resolvePluginFromNameOrObject($plugin);
        $roomId = $room !== null ? $this->resolveRoomFromIdentOrObject($room) : null;

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

    public function handleRoomEvent(RoomSourcedEvent $event, Message $message = null): \Generator
    {
        $eventId = $event->getEventId();
        $this->logger->log(Level::DEBUG, "Processing event #{$eventId} for plugins");

        $promises = $this->invokeHandlersForEvent($event);

        if ($message !== null) {
            foreach ($this->messageHandlers as $handler) {
                if ($promise = $this->invokeCallbackAsPromise($handler, $message)) { // some callbacks may be synchronous
                    $promises[] = $promise;
                }
            }
        }

        if ($message instanceof Command) {
            $userId = $message->getUserId();
            $room = $message->getRoom()->getIdentifier()->getIdentString();
            $command = $message->getCommandName();

            if (yield from $this->banStorage->isBanned($message->getRoom(), $userId)) {
                $this->logger->log(Level::DEBUG,
                    "User #{$userId} is banned, ignoring event #{$eventId} for plugin command endpoints"
                    . " (command: {$command})"
                );
            } else if (isset($this->commandMap[$room][$command])) {
                /** @var Plugin $plugin */
                /** @var PluginCommandEndpoint $endpoint */
                list($plugin, $endpoint) = $this->commandMap[$room][$command];

                // just a sanity check, shouldn't ever be false but in case something goes horribly wrong
                if ($this->isPluginEnabledForRoom($plugin, $room)) {
                    if ($promise = $this->invokeCallbackAsPromise($endpoint->getCallback(), $message)) { // some callbacks may be synchronous
                        $promises[] = $promise;
                    }
                } else {
                    $this->logger->log(Level::DEBUG,
                        "Command {$command} still present for {$room} but plugin {$plugin->getName()}"
                      . " is disabled! (endpoint: {$endpoint->getName()})"
                    );
                }
            }
        }

        yield all($promises);

        $this->logger->log(Level::DEBUG, "Event #{$eventId} processed for plugins");
    }
}
