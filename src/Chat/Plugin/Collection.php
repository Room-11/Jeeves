<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

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
    private $plugins = [];

    public function __construct(CommandFactory $commandFactory, BanList $banList)
    {
        $this->commandFactory = $commandFactory;
        $this->banList        = $banList;
    }

    public function register(Plugin $plugin): Collection
    {
        $this->plugins[] = $plugin;

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

        foreach ($this->plugins as $plugin) {
            yield from $plugin->handle($command);
        }
    }
}
