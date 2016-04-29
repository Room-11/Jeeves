<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Factory as CommandFactory;
use Room11\Jeeves\Storage\Ban as BanList;
use Room11\Jeeves\Chat\Message\Message;
use Room11\Jeeves\Chat\Message\UserMessage;

class Collection
{
    private $commandFactory;

    private $banList;

    /**
     * @var Plugin[]
     */
    private $messagePlugins = [];

    /**
     * @var Plugin[][]
     */
    private $commandPlugins = [];

    public function __construct(CommandFactory $commandFactory, BanList $banList)
    {
        $this->commandFactory = $commandFactory;
        $this->banList        = $banList;
    }

    public function register(Plugin $plugin): Collection
    {
        if ($plugin->handlesAllMessages()) {
            $this->messagePlugins[] = $plugin;
        }

        foreach ($plugin->getHandledCommands() as $commandName) {
            $this->commandPlugins[$commandName][] = $plugin;
        }

        return $this;
    }

    public function handle(Message $message): \Generator
    {
        $command = $this->commandFactory->build($message);

        if ($message instanceof UserMessage) {
            if (yield from $this->banList->isBanned($message->getUserId())) {
                return;
            }
        }

        foreach ($this->messagePlugins as $plugin) {
            yield from $plugin->handleMessage($command);
        }

        if ($command instanceof Command && isset($this->commandPlugins[$command->getCommand()])) {
            foreach ($this->commandPlugins[$command->getCommand()] as $plugin) {
                yield from $plugin->handleCommand($command);
            }
        }
    }
}
