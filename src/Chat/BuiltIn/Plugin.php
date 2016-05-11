<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\BuiltIn;

use Room11\Jeeves\Chat\BuiltInCommand;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Chat\PluginManager;
use Room11\Jeeves\Storage\Admin as AdminStorage;

class Plugin implements BuiltInCommand
{
    private $pluginManager;
    private $chatClient;
    private $adminStorage;

    public function __construct(PluginManager $pluginManager, ChatClient $chatClient, AdminStorage $adminStorage)
    {
        $this->pluginManager = $pluginManager;
        $this->chatClient = $chatClient;
        $this->adminStorage = $adminStorage;
    }

    /**
     * Handle a command message
     *
     * @param CommandMessage $command
     * @return \Generator
     */
    public function handleCommand(CommandMessage $command): \Generator
    {
        switch ($command->getParameter(0)) {
            case 'list':
                yield from $this->list($command);
                return;

            case 'enable':
                yield from $this->enable($command);
                return;

            case 'disable':
                yield from $this->disable($command);
                return;
        }

        yield from $this->chatClient->postReply($command, "Syntax: plugin [list|disable|enable] [plugin-name]");
    }

    private function listPlugins(CommandMessage $command): \Generator
    {
        $result = 'Currently registered plugins:';

        foreach ($this->pluginManager->getRegisteredPlugins() as $plugin) {
            $check = $this->pluginManager->isPluginEnabledForRoom($plugin, $command->getRoom()) ? 'X' : ' ';
            $result .= "\n[{$check}] {$plugin->getName()} - {$plugin->getDescription()}";
        }

        yield from $this->chatClient->postMessage($command->getRoom(), $result, true);
    }

    private function listPluginEndpoints(string $plugin, CommandMessage $command): \Generator
    {
        if (!$this->pluginManager->isPluginRegistered($plugin)) {
            yield from $this->chatClient->postReply($command, "Invalid plugin name");
            return;
        }

        $plugin = $this->pluginManager->getPluginByName($plugin);
        $enabled = $this->pluginManager->isPluginEnabledForRoom($plugin, $command->getRoom())
            ? 'enabled'
            : 'disabled';

        $result = "Command endpoints for plugin '{$plugin->getName()}' ({$enabled}):";

        foreach ($this->pluginManager->getPluginCommandEndpoints($plugin, $command->getRoom()) as $name => $endpoint) {
            if ($endpoint['mapped_commands']) {
                $check = 'X';
                $map = 'Mapped commands: ' . implode(', ', $endpoint['mapped_commands']);
            } else {
                $check = ' ';
                $map = 'No mapped commands';
            }

            $result .= "\n[{$check}] {$name} - {$endpoint['description']} (Default command: {$endpoint['default_command']}, {$map})";
        }

        yield from $this->chatClient->postMessage($command->getRoom(), $result, true);
    }

    private function list(CommandMessage $command): \Generator
    {
        if (null !== $plugin = $command->getParameter(1)) {
            yield from $this->listPluginEndpoints($plugin, $command);
        } else {
            yield from $this->listPlugins($command);
        }
    }

    private function enable(CommandMessage $command): \Generator
    {
        if (!yield $this->adminStorage->isAdmin($command->getRoom(), $command->getUserId())) {
            yield from $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            return;
        }

        if (null === $plugin = $command->getParameter(1)) {
            yield from $this->chatClient->postReply($command, "No plugin name supplied");
            return;
        }

        if (!$this->pluginManager->isPluginRegistered($plugin)) {
            yield from $this->chatClient->postReply($command, "Invalid plugin name");
            return;
        }

        if ($this->pluginManager->isPluginEnabledForRoom($plugin, $command->getRoom())) {
            yield from $this->chatClient->postReply($command, "Plugin already enabled in this room");
            return;
        }

        yield $this->pluginManager->enablePluginForRoom($plugin, $command->getRoom());
        yield from $this->chatClient->postMessage($command->getRoom(), "Plugin '{$plugin}' is now enabled in this room");
    }

    private function disable(CommandMessage $command): \Generator
    {
        if (!yield $this->adminStorage->isAdmin($command->getRoom(), $command->getUserId())) {
            yield from $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            return;
        }

        if (null === $plugin = $command->getParameter(1)) {
            yield from $this->chatClient->postReply($command, "No plugin name supplied");
            return;
        }

        if (!$this->pluginManager->isPluginRegistered($plugin)) {
            yield from $this->chatClient->postReply($command, "Invalid plugin name");
            return;
        }

        if (!$this->pluginManager->isPluginEnabledForRoom($plugin, $command->getRoom())) {
            yield from $this->chatClient->postReply($command, "Plugin already disabled in this room");
            return;
        }

        yield $this->pluginManager->disablePluginForRoom($plugin, $command->getRoom());
        yield from $this->chatClient->postMessage($command->getRoom(), "Plugin '{$plugin}' is now disabled in this room");
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
