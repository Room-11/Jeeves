<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat;

use Room11\Jeeves\Chat\Event\Event;
use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Event\UserSourcedEvent;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Storage\Ban as BanStorage;
use function Amp\all;
use function Amp\resolve;

class PluginManager
{
    private $banStorage;
    private $logger;

    /**
     * @var Plugin[]
     */
    private $messagePlugins = [];

    /**
     * @var Plugin[][]
     */
    private $commandPlugins = [];

    public function __construct(BanStorage $banStorage, Logger $logger)
    {
        $this->banStorage = $banStorage;
        $this->logger = $logger;
    }

    public function register(Plugin $plugin): PluginManager
    {
        if ($plugin->handlesAllMessages()) {
            $this->messagePlugins[] = $plugin;
        }

        foreach ($plugin->getHandledCommands() as $commandName) {
            $this->commandPlugins[$commandName][] = $plugin;
        }

        return $this;
    }

    public function handle(Event $event): \Generator
    {
        if (!$event instanceof MessageEvent) {
            /* todo: handle other event types. No plugins actually use other event types at the
               moment so this is OK, fixing it requires more refactoring */
            return;
        }

        $eventId = $event->getEventId();
        $this->logger->log(Level::DEBUG, "Processing event #{$eventId} for plugins");

        if ($event instanceof UserSourcedEvent) {
            $userId = $event->getUserId();

            if (yield from $this->banStorage->isBanned($userId)) {
                $this->logger->log(Level::DEBUG, "User #{$userId} is banned, ignoring event #{$eventId} for plugins");
                return;
            }
        }


        $message = $event->getMessage();

        $promises = [];

        foreach ($this->messagePlugins as $plugin) {
            $this->logger->log(Level::DEBUG, "Passing event #{$eventId} to plugin " . get_class($plugin));
            $promises[] = resolve($plugin->handleMessage($message));
        }

        if ($message instanceof Command && isset($this->commandPlugins[$message->getCommandName()])) {
            foreach ($this->commandPlugins[$message->getCommandName()] as $plugin) {
                $this->logger->log(Level::DEBUG, "Passing event #{$eventId} to plugin " . get_class($plugin));
                $promises[] = resolve($plugin->handleCommand($message));
            }
        }

        $this->logger->log(Level::DEBUG, "Event #{$eventId} matched " . count($promises) . ' plugins total');

        yield all($promises);

        $this->logger->log(Level::DEBUG, "Event #{$eventId} processed for plugins");
    }
}
