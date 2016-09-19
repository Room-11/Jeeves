<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\WebSocket;

use Room11\Jeeves\Chat\Event\GlobalEvent;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\System\PluginManager;

class GlobalEventDispatcher
{
    const BUFFER_SIZE = 20;

    private $pluginManager;
    private $logger;

    private $recentGlobalEventBuffer = [];

    public function __construct(
        PluginManager $pluginManager,
        Logger $logger
    ) {
        $this->pluginManager = $pluginManager;
        $this->logger = $logger;
    }

    public function processEvent(GlobalEvent $event): \Generator
    {
        $eventId = $event->getId();

        if (in_array($event->getId(), $this->recentGlobalEventBuffer)) {
            return;
        }

        $this->recentGlobalEventBuffer[] = $event->getId();
        if (count($this->recentGlobalEventBuffer) > self::BUFFER_SIZE) {
            array_shift($this->recentGlobalEventBuffer);
        }

        $this->logger->log(Level::DEBUG, "Processing global event #{$eventId} for plugins");
        yield $this->pluginManager->handleGlobalEvent($event);
        $this->logger->log(Level::DEBUG, "Event #{$eventId} processed for plugins");
    }
}
