<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat;

use Amp\Promise;
use Amp\Success;
use Psr\Log\LoggerInterface as Logger;
use Room11\Jeeves\System\BuiltInActionManager;
use Room11\Jeeves\System\PluginManager;
use Room11\StackChat\Auth\SessionTracker;
use Room11\StackChat\Entities\ChatMessage;
use Room11\StackChat\Event\Event;
use Room11\StackChat\Event\GlobalEvent;
use Room11\StackChat\Room\Room;
use Room11\StackChat\WebSocket\EventDispatcher;
use function Amp\resolve;

class WebSocketEventDispatcher implements EventDispatcher
{
    private $pluginManager;
    private $builtInActionManager;
    private $commandFactory;
    private $sessions;
    private $presenceManager;
    private $logger;
    private $recentGlobalEventBuffer;
    private $devMode;

    public function __construct(
        PluginManager $pluginManager,
        BuiltInActionManager $builtInActionManager,
        CommandFactory $commandFactory,
        SessionTracker $sessions,
        PresenceManager $presenceManager,
        Logger $logger,
        FixedSizeEventBuffer $recentGlobalEventBuffer,
        bool $devMode
    ) {
        $this->pluginManager = $pluginManager;
        $this->builtInActionManager = $builtInActionManager;
        $this->commandFactory = $commandFactory;
        $this->sessions = $sessions;
        $this->presenceManager = $presenceManager;
        $this->logger = $logger;
        $this->recentGlobalEventBuffer = $recentGlobalEventBuffer;
        $this->devMode = $devMode;
    }

    private function processGlobalEvent(GlobalEvent $event)
    {
        $eventId = $event->getId();

        try {
            if ($this->recentGlobalEventBuffer->contains($eventId)) {
                return;
            }

            $this->recentGlobalEventBuffer->push($eventId);

            $this->logger->debug("Processing global event #{$eventId} for built in event handlers", ['event' => $event]);
            yield $this->builtInActionManager->handleEvent($event);
            $this->logger->debug("Event #{$eventId} processed for built in event handlers");
        } catch (\Throwable $e) {
            $this->logger->error("Something went wrong while processing event #{$eventId}: {$e}");
        }
    }

    private function processRoomEvent(Event $event)
    {
        $eventId = $event->getId();

        try {
            $this->logger->debug("Processing room event #{$eventId} for built in event handlers", ['event' => $event]);
            yield $this->builtInActionManager->handleEvent($event);

            $this->logger->debug("Event #{$eventId} processed for built in event handlers, processing for plugins");
            yield $this->pluginManager->handleEvent($event);

            $this->logger->debug("Event #{$eventId} processed for plugins");
        } catch (\Throwable $e) {
            $this->logger->error("Something went wrong while processing event #{$eventId}: {$e}");
        }
    }

    private function processCommandMessage(ChatMessage $message)
    {
        $logId = "#{$message->getId()} (event #{$message->getEvent()->getId()})";

        try {
            $command = yield $this->commandFactory->buildCommand($message);

            $this->logger->debug("Processing command message {$logId} for built in commands");
            yield $this->builtInActionManager->handleCommand($command);

            $this->logger->debug("Command message {$logId} processed for built in commands, processing for plugins");
            yield $this->pluginManager->handleCommand($command);

            $this->logger->debug("Command message {$logId} processed for plugins");
        } catch (\Throwable $e) {
            $this->logger->error("Something went wrong while processing command message {$logId}: {$e}");
        }
    }

    private function processNonCommandMessage(ChatMessage $message)
    {
        $logId = "#{$message->getId()} (event #{$message->getEvent()->getId()})";

        try {
            $this->logger->debug("Processing non-command message {$logId} for plugins");
            yield $this->pluginManager->handleMessage($message);

            $this->logger->debug("Non-command message {$logId} processed for plugins");
        } catch (\Throwable $e) {
            $this->logger->error("Something went wrong while processing non-command message {$logId}: {$e}");
        }
    }

    private function processDisconnect(Room $room)
    {
        yield $this->pluginManager->disableAllPluginsForRoom($room);
        return $this->presenceManager->processDisconnect($room);
    }

    public function onWebSocketEvent(Event $event): Promise
    {
        return resolve(
            $event instanceof GlobalEvent
                ? $this->processGlobalEvent($event)
                : $this->processRoomEvent($event)
        );
    }

    public function onConnect(Room $room): Promise
    {
        return $this->pluginManager->enableAllPluginsForRoom($room);
    }

    public function onDisconnect(Room $room): Promise
    {
        return resolve($this->processDisconnect($room));
    }

    public function onMessageEvent(ChatMessage $message): Promise
    {
        $session = $this->sessions->getSessionForRoom($message->getRoom());

        if ($message->getUserId() === $session->getUser()->getId()) {
            return new Success;
        }

        return $this->commandFactory->isCommandMessage($message)
            ? resolve($this->processCommandMessage($message))
            : resolve($this->processNonCommandMessage($message));
    }
}
