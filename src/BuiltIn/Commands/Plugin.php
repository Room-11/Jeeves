<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\PostFlags;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\Jeeves\System\PluginManager;
use function Amp\resolve;

class Plugin implements BuiltInCommand
{
    private $pluginManager;
    private $chatClient;
    private $adminStorage;

    const COMMAND_HELP_TEXT =
        "Sub-commands (* indicates admin-only):"
        . "\n"
        . "\n help     - display this message"
        . "\n list     - display a list of plugins, or a list of endpoints for the specified plugin."
        . "\n             Syntax: plugin list [<name>]"
        . "\n *enable  - Enable a plugin in this room."
        . "\n             Syntax: plugin enable <name>"
        . "\n *disable - Disable a plugin in this room."
        . "\n             Syntax: plugin disable <name>"
        . "\n status   - Query whether a plugin is enabled in this room."
        . "\n             Syntax: plugin status <name>"
    ;

    public function __construct(PluginManager $pluginManager, ChatClient $chatClient, AdminStorage $adminStorage)
    {
        $this->pluginManager = $pluginManager;
        $this->chatClient = $chatClient;
        $this->adminStorage = $adminStorage;
    }

    private function listPlugins(CommandMessage $command)
    {
        $result = 'Currently registered plugins:';

        foreach ($this->pluginManager->getRegisteredPlugins() as $plugin) {
            $check = $this->pluginManager->isPluginEnabledForRoom($plugin, $command->getRoom()) ? 'X' : ' ';
            $result .= "\n[{$check}] {$plugin->getName()} - {$plugin->getDescription()}";
        }

        return $this->chatClient->postMessage($command, $result, PostFlags::FIXED_FONT);
    }

    private function listPluginEndpoints(string $plugin, CommandMessage $command)
    {
        if (!$this->pluginManager->isPluginRegistered($plugin)) {
            return $this->chatClient->postReply($command, 'Invalid plugin name');
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

        return $this->chatClient->postMessage($command, $result, PostFlags::FIXED_FONT);
    }

    private function list(CommandMessage $command): Promise
    {
        $plugin = $command->getParameter(1);

        return $plugin === null
            ? $this->listPlugins($command)
            : $this->listPluginEndpoints($plugin, $command);
    }

    private function enable(CommandMessage $command): Promise
    {
        return resolve(function() use($command) {
            if (!yield $this->adminStorage->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            }

            if (null === $plugin = $command->getParameter(1)) {
                return $this->chatClient->postReply($command, 'No plugin name supplied');
            }

            if (!$this->pluginManager->isPluginRegistered($plugin)) {
                return $this->chatClient->postReply($command, 'Invalid plugin name');
            }

            if ($this->pluginManager->isPluginEnabledForRoom($plugin, $command->getRoom())) {
                return $this->chatClient->postReply($command, 'Plugin already enabled in this room');
            }

            yield $this->pluginManager->enablePluginForRoom($plugin, $command->getRoom());

            return $this->chatClient->postMessage($command, "Plugin '{$plugin}' is now enabled in this room");
        });
    }

    private function disable(CommandMessage $command): Promise
    {
        return resolve(function() use($command) {
            if (!yield $this->adminStorage->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            }

            if (null === $plugin = $command->getParameter(1)) {
                return $this->chatClient->postReply($command, 'No plugin name supplied');
            }

            if (!$this->pluginManager->isPluginRegistered($plugin)) {
                return $this->chatClient->postReply($command, 'Invalid plugin name');
            }

            if (!$this->pluginManager->isPluginEnabledForRoom($plugin, $command->getRoom())) {
                return $this->chatClient->postReply($command, 'Plugin already disabled in this room');
            }

            yield $this->pluginManager->disablePluginForRoom($plugin, $command->getRoom());

            return $this->chatClient->postMessage($command, "Plugin '{$plugin}' is now disabled in this room");
        });
    }

    private function status(CommandMessage $command): Promise
    {
        if (null === $plugin = $command->getParameter(1)) {
            return $this->chatClient->postReply($command, 'No plugin name supplied');
        }

        if (!$this->pluginManager->isPluginRegistered($plugin)) {
            return $this->chatClient->postReply($command, 'Invalid plugin name');
        }

        $message = $this->pluginManager->isPluginEnabledForRoom($plugin, $command->getRoom())
            ? "Plugin '{$plugin}' is currently enabled in this room"
            : "Plugin '{$plugin}' is currently disabled in this room";

        return $this->chatClient->postMessage($command, $message);
    }

    private function showCommandHelp(CommandMessage $command): Promise
    {
        return $this->chatClient->postMessage($command, self::COMMAND_HELP_TEXT, PostFlags::FIXED_FONT);
    }

    /**
     * Handle a command message
     *
     * @param CommandMessage $command
     * @return Promise
     */
    public function handleCommand(CommandMessage $command): Promise
    {
        switch ($command->getParameter(0)) {
            case 'help':    return $this->showCommandHelp($command);
            case 'list':    return $this->list($command);
            case 'enable':  return $this->enable($command);
            case 'disable': return $this->disable($command);
            case 'status':  return $this->status($command);
        }

        return $this->chatClient->postReply($command, 'Syntax: plugin [list|disable|enable] [plugin-name]');
    }

    /**
     * Get a list of specific commands handled by this built-in
     *
     * @return BuiltInCommandInfo[]
     */
    public function getCommandInfo(): array
    {
        return [
            new BuiltInCommandInfo('plugin', "Manage plugins. Use 'plugin help' for details."),
        ];
    }
}
