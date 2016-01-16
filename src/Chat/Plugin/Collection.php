<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Command\Factory as CommandFactory;
use Room11\Jeeves\Chat\Message\Message;

class Collection
{
    private $commandFactory;

    private $plugins = [];

    public function __construct(CommandFactory $commandFactory)
    {
        $this->commandFactory = $commandFactory;
    }

    public function register(Plugin $plugin): Collection
    {
        $this->plugins[] = $plugin;

        return $this;
    }

    public function handle(Message $message): \Generator
    {
        $command = $this->commandFactory->build($message);

        foreach ($this->plugins as $plugin) {
            yield from $plugin->handle($command);
        }
    }
}
