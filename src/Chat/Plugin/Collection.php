<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Command\Message;

class Collection implements Plugin
{
    private $plugins = [];

    public function register(Plugin $plugin): Collection
    {
        $this->plugins[] = $plugin;

        return $this;
    }

    public function handle(Message $message): \Generator
    {
        foreach ($this->plugins as $plugin) {
            yield from $plugin->handle($message);
        }
    }
}
