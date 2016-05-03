<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat;

use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\Chat\Event\Event;
use Room11\Jeeves\Chat\Event\UserSourcedEvent;
use function Amp\all;
use function Amp\resolve;

class PluginManager
{
    private $adminStorage;

    private $banStorage;

    /**
     * @var Plugin[]
     */
    private $messagePlugins = [];

    /**
     * @var Plugin[][]
     */
    private $commandPlugins = [];

    public function __construct(AdminStorage $adminStorage, BanStorage $banStorage)
    {
        $this->adminStorage = $adminStorage;
        $this->banStorage = $banStorage;
    }

    public function getAdminStorage(): AdminStorage
    {
        return $this->adminStorage;
    }

    public function getBanStorage(): BanStorage
    {
        return $this->adminStorage;
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

        if ($event instanceof UserSourcedEvent) {
            if (yield from $this->banStorage->isBanned($event->getUserId())) {
                return;
            }
        }

        $message = $event->getMessage();

        $promises = [];

        foreach ($this->messagePlugins as $plugin) {
            $promises[] = resolve($plugin->handleMessage($message));
        }

        if ($message instanceof Command && isset($this->commandPlugins[$message->getCommandName()])) {
            foreach ($this->commandPlugins[$message->getCommandName()] as $plugin) {
                $promises[] = resolve($plugin->handleCommand($message));
            }
        }

        yield all($promises);
    }
}
