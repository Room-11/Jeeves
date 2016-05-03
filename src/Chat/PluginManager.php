<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat;

use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Message\Factory as MessageFactory;
use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Plugin\Plugin;
use Room11\Jeeves\Storage\Ban as BanList;
use Room11\Jeeves\Chat\Event\Event;
use Room11\Jeeves\Chat\Event\UserSourcedEvent;

class PluginManager
{
    private $messageFactory;

    private $banList;

    /**
     * @var Plugin[]
     */
    private $messagePlugins = [];

    /**
     * @var Plugin[][]
     */
    private $commandPlugins = [];

    public function __construct(MessageFactory $messageFactory, BanList $banList)
    {
        $this->messageFactory = $messageFactory;
        $this->banList        = $banList;
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
            if (yield from $this->banList->isBanned($event->getUserId())) {
                return;
            }
        }

        $message = $this->messageFactory->build($event);

        foreach ($this->messagePlugins as $plugin) {
            yield from $plugin->handleMessage($message);
        }

        if ($message instanceof Command && isset($this->commandPlugins[$message->getCommandName()])) {
            foreach ($this->commandPlugins[$message->getCommandName()] as $plugin) {
                yield from $plugin->handleCommand($message);
            }
        }
    }
}
