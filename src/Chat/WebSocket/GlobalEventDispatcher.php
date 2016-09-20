<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\WebSocket;

use Ds\Deque;
use Room11\Jeeves\Chat\Event\GlobalEvent;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\System\PluginManager;

class GlobalEventDispatcher
{
    const BUFFER_SIZE = 20;

    private $pluginManager;
    private $logger;

    private $recentGlobalEventBuffer;

    public function __construct(
        PluginManager $pluginManager,
        Logger $logger
    ) {
        $this->pluginManager = $pluginManager;
        $this->logger = $logger;

        $this->recentGlobalEventBuffer = new Deque;
    }

    public function processEvent(GlobalEvent $event): \Generator
    {
        $eventId = $event->getId();

        if ($this->recentGlobalEventBuffer->contains($eventId)) {
            return;
        }

        $this->recentGlobalEventBuffer[] = $eventId;
        if ($this->recentGlobalEventBuffer->count() > self::BUFFER_SIZE) {
            $this->recentGlobalEventBuffer->shift();
        }

        $this->logger->log(Level::DEBUG, "Processing global event #{$eventId} for plugins");
        yield $this->pluginManager->handleGlobalEvent($event);
        $this->logger->log(Level::DEBUG, "Event #{$eventId} processed for plugins");
    }
}
