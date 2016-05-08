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

        $filterSets = [
            $this->typeFilteredEventHandlers[$event->getTypeId()] ?? [],
            $this->roomFilteredEventHandlers[$room] ?? [],
            $this->filteredEventHandlers,
        ];

        $promises = [];

        foreach ($filterSets as $filterSet) foreach ($filterSet as list($plugin, $filter)) {
            /** @var Filter $filter */
            if (($room === null || $this->isPluginEnabledForRoom($plugin, $room))
                && ($promise = $filter->executeForEvent($event))) {
                $promises[] = $promise;
            }
        }

        return $promises;
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
        $pluginName = $plugin->getName();

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

                $endpoints[$endpoint->getName()] = $endpoint;
            }
            $this->logger->log(Level::DEBUG, "Found " . count($endpoints) . " command endpoints for plugin '{$pluginName}'");

            foreach ($plugin->getEventHandlers() as $filter => $handler) {
                $filter = $this->filterBuilder->build($filter, $handler);

                $rooms = $filter->getRooms();
                $types = $filter->getTypes();

                if (empty($rooms) && empty($types)) {
                    $filters[] = [$pluginName, $filter];
                    continue;
                }

                foreach ($rooms as $room) {
                    $roomFilters[$room][] = [$pluginName, $filter];
                }

                foreach ($types as $type) {
                    $typeFilters[$type][] = [$pluginName, $filter];
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
                $this->roomFilteredEventHandlers[$type][] = $handler;
            }
        }

        $this->logger->log(Level::DEBUG, "Registered plugin '{$pluginName}' successfully");
    }

    /**
     * @param Plugin|string $plugin
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @return bool
     */
    public function isPluginEnabledForRoom($plugin, $room): bool
    {
        $pluginName = $plugin instanceof Plugin ? $plugin->getName() : (string)$plugin;

        if ($room instanceof ChatRoom) {
            $room = $room->getIdentifier();
        }
        $roomId = $room instanceof ChatRoomIdentifier ? $room->getIdentString() : (string)$room;

        return isset($this->enabledPlugins[$roomId][$pluginName]);
    }

    /**
     * @param Plugin|string $plugin
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @todo persist this
     */
    public function disablePluginForRoom($plugin, $room) /*: void*/
    {
        $pluginName = $plugin instanceof Plugin ? $plugin->getName() : (string)$plugin;

        if ($room instanceof ChatRoom) {
            $room = $room->getIdentifier();
        }
        $roomId = $room instanceof ChatRoomIdentifier ? $room->getIdentString() : (string)$room;

        $this->logger->log(Level::DEBUG, "Disabling plugin '{$pluginName}' for room '{$roomId}'");
        unset($this->enabledPlugins[$roomId][$pluginName], $this->commandMap[$roomId]);
    }

    /**
     * @param Plugin|string $plugin
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @todo persist this
     * @todo make mappable commands
     */
    public function enablePluginForRoom($plugin, $room) /*: void*/
    {
        $pluginName = $plugin instanceof Plugin ? $plugin->getName() : (string)$plugin;

        if ($room instanceof ChatRoom) {
            $room = $room->getIdentifier();
        }
        $roomId = $room instanceof ChatRoomIdentifier ? $room->getIdentString() : (string)$room;

        if (!isset($this->registeredPlugins[$pluginName])) {
            throw new \LogicException("Cannot enable plugin '{$pluginName}' for room '{$roomId}': not registered");
        }

        $this->logger->log(Level::DEBUG, "Enabling plugin '{$pluginName}' for room '{$roomId}'");
        $this->enabledPlugins[$roomId][$pluginName] = true;

        $plugin = $this->registeredPlugins[$pluginName];

        /** @var PluginCommandEndpoint $endpoint */
        foreach ($this->commandEndpoints[$pluginName] as $endpoint) {
            if (null !== $command = $endpoint->getDefaultCommand()) {
                $this->commandMap[$roomId][$endpoint->getDefaultCommand()] = [$plugin, $endpoint];
            }
        }
    }

    /**
     * @return Plugin[]
     */
    public function getRegisteredPlugins(): array
    {
        return $this->registeredPlugins;
    }

    public function handleRoomEvent(RoomSourcedEvent $event, Message $message = null): \Generator
    {
        $eventId = $event->getEventId();
        $this->logger->log(Level::DEBUG, "Processing event #{$eventId} for plugins");

        $promises = $this->invokeHandlersForEvent($event);

        foreach ($this->messageHandlers as $handler) {
            if ($promise = $this->invokeCallbackAsPromise($handler)) { // some callbacks may be synchronous
                $promises[] = $promise;
            }
        }

        if ($message instanceof Command) {
            $userId = $message->getUserId();
            $room = $message->getRoom()->getIdentifier()->getIdentString();
            $command = $message->getCommandName();

            if (!yield from $this->banStorage->isBanned($userId) && isset($this->commandMap[$room][$command])) {
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
            } else {
                $this->logger->log(Level::DEBUG,
                    "User #{$userId} is banned, ignoring event #{$eventId} for plugins"
                  . " (command: {$message->getCommandName()})"
                );
            }
        }

        yield all($promises);

        $this->logger->log(Level::DEBUG, "Event #{$eventId} processed for plugins");
    }
}
