<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\BuiltIn;

use Room11\Jeeves\Chat\BuiltInCommand;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\PluginManager;

class Plugin implements BuiltInCommand
{
    private $pluginManager;
    private $chatClient;

    public function __construct(PluginManager $pluginManager, ChatClient $chatClient)
    {
        $this->pluginManager = $pluginManager;
        $this->chatClient = $chatClient;
    }

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator
    {
        switch ($command->getParameter(0)) {
            case 'list': return yield from $this->list($command);
            case 'enable': return yield from $this->enable($command);
            case 'disable': return yield from $this->disable($command);
        }

        return yield from $this->chatClient->postReply($command, "Syntax: plugin [list|disable|enable] [name]");
    }

    private function list(Command $command): \Generator
    {
        $result = 'Currently registered plugins:';

        foreach ($this->pluginManager->getRegisteredPlugins() as $plugin) {
            $enabled = $this->pluginManager->isPluginEnabledForRoom($plugin, $command->getRoom())
                ? 'enabled'
                : 'disabled';

            $result .= "\n  {$plugin->getName()} - {$plugin->getDescription()} ({$enabled})";
        }

        yield from $this->chatClient->postReply($command, $result);
    }

    private function enable(Command $command): \Generator
    {
        if (null === $plugin = $command->getParameter(1)) {
            return yield from $this->chatClient->postReply($command, "No plugin name supplied");
        }

        if (!$this->pluginManager->hasPluginRegistered($plugin)) {
            return yield from $this->chatClient->postReply($command, "Invalid plugin name");
        }

        if ($this->pluginManager->isPluginEnabledForRoom($plugin, $command->getRoom())) {
            return yield from $this->chatClient->postReply($command, "Plugin already enabled in this room");
        }

        $this->pluginManager->enablePluginForRoom($plugin, $command->getRoom());
        return yield from $this->chatClient->postReply($command, "Plugin '{$plugin}' is now enabled in this room");
    }

    private function disable(Command $command): \Generator
    {
        if (null === $plugin = $command->getParameter(1)) {
            return yield from $this->chatClient->postReply($command, "No plugin name supplied");
        }

        if (!$this->pluginManager->hasPluginRegistered($plugin)) {
            return yield from $this->chatClient->postReply($command, "Invalid plugin name");
        }

        if (!$this->pluginManager->isPluginEnabledForRoom($plugin, $command->getRoom())) {
            return yield from $this->chatClient->postReply($command, "Plugin already disabled in this room");
        }

        $this->pluginManager->disablePluginForRoom($plugin, $command->getRoom());
        return yield from $this->chatClient->postReply($command, "Plugin '{$plugin}' is now disabled in this room");
    }

    /**
     * Get a list of specific commands handled by this built-in
     *
     * @return string[]
     */
    public function getCommandNames(): array
    {
        return ['plugin'];
    }
}
