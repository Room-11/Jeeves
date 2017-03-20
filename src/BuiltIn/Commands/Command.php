<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Command as CommandMessage;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\CommandAlias as CommandAliasStorage;
use Room11\Jeeves\System\BuiltInActionManager;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\Jeeves\System\PluginManager;
use Room11\StackChat\Client\Client;
use Room11\StackChat\Client\PostFlags;
use function Amp\resolve;

class Command implements BuiltInCommand
{
    const RESPONSE_MESSAGES = [
        'command_already_mapped' => "Command '%s' is already mapped. Use `!!command list` to display the currently "
                                  . "mapped commands.",
        'command_built_in'       => "Command '%s' is built in and cannot be altered",
        'command_map_success'    => "Command '%s' is now mapped to %s # %s",
        'command_not_mapped'     => "Command '%s' is not currently mapped",
        'command_unmap_success'  => "Command '%s' is no longer mapped",
        'multiple_endpoints'     => "Plugin '%1\$s' provides %2\$d endpoints, you must specify the endpoint to which "
                                  . "the command should be mapped. Use `!!plugin list %1\$s` to display information "
                                  . "about the available endpoints.",
        'plugin_not_enabled'     => "Plugin '%s' is not currently enabled",
        'syntax'                 => "    Syntax: command [map|remap] command plugin [endpoint]\n"
                                  . "            command unmap command\n"
                                  . "            command clone new-command existing-command\n"
                                  . "            command list",
        'unexpected_error'       => "Something really unexpected happened: %s",
        'unknown_endpoint'       => "Unknown endpoint name '%s' for plugin '%s'",
        'unknown_plugin'         => "Unknown plugin '%s'. Use `!!plugin list` to display the currently registered "
                                  . "plugins.",
        'user_not_admin'         => "I'm sorry Dave, I'm afraid I can't do that",
    ];

    const COMMAND_HELP_TEXT =
        "Sub-commands (* indicates admin-only):"
        . "\n"
        . "\n help   - display this message"
        . "\n list   - display a list of the currently mapped commands."
        . "\n *clone - Copy a command mapping. This is preferable to using an alias where possible, as it ensures that help text is as helpful as possible."
        . "\n           Syntax: command clone <new command> <existing command>"
        . "\n *map   - Map a command to a plugin endpoint. The plugin name must be specified, the endpoint name is optional when the plugin only has one endpoint."
        . "\n           Syntax: command map <command> <plugin> [<endpoint>]"
        . "\n *remap - Alter an existing command mapping."
        . "\n           Syntax: command remap <command> <plugin> [<endpoint>]"
        . "\n *unmap - Remove an existing command mapping."
        . "\n           Syntax: command unmap <command>"
    ;

    private $pluginManager;
    private $builtInCommandManager;
    private $chatClient;
    private $adminStorage;
    private $aliasStorage;

    private static function message(string $name, ...$args) {
        return vsprintf(self::RESPONSE_MESSAGES[$name], $args);
    }

    private /* async */ function map(CommandMessage $command): \Generator
    {
        $room = $command->getRoom();

        if (!yield $this->adminStorage->isAdmin($room, $command->getUserId())) {
            return $this->chatClient->postReply($command, self::message('user_not_admin'));
        }

        if (!$command->hasParameters(3)) {
            return $this->chatClient->postReply($command, self::message('syntax'));
        }

        $cmd = $command->getParameter(1);
        $pluginName = $command->getParameter(2);
        $endpointName = $command->getParameter(3);

        if ($this->builtInCommandManager->hasRegisteredCommand($cmd)) {
            return $this->chatClient->postReply($command, self::message('command_built_in', $cmd));
        }

        if ($this->pluginManager->isCommandMappedForRoom($room, $cmd)) {
            return $this->chatClient->postReply($command, self::message('command_already_mapped', $cmd));
        }

        if (!$this->pluginManager->isPluginRegistered($pluginName)) {
            return $this->chatClient->postReply($command, self::message('unknown_plugin', $pluginName));
        }

        $plugin = $this->pluginManager->getPluginByName($pluginName);

        if (!$this->pluginManager->isPluginEnabledForRoom($plugin, $room)) {
            return $this->chatClient->postReply($command, self::message('plugin_not_enabled', $plugin->getName()));
        }

        $endpoints = $this->pluginManager->getPluginCommandEndpoints($plugin);

        if ($endpointName === null) {
            $count = count($endpoints);

            if ($count > 1) {
                return $this->chatClient->postReply($command, self::message('multiple_endpoints', $pluginName, $count));
            }

            reset($endpoints);
            $endpointName = key($endpoints);
        } else {
            $validEndpoint = false;
            foreach ($endpoints as $name => $info) {
                if (strtolower($name) === strtolower($endpointName)) {
                    $validEndpoint = true;
                }
            }

            if (!$validEndpoint) {
                return $this->chatClient->postReply($command, self::message('unknown_endpoint', $endpointName, $pluginName));
            }
        }

        yield $this->pluginManager->mapCommandForRoom($room, $plugin, $endpointName, $cmd);

        return $this->chatClient->postMessage(
            $command,
            self::message('command_map_success', $cmd, $plugin->getName(), $endpointName)
        );
    }

    private /* async */ function unmap(CommandMessage $command): \Generator
    {
        $room = $command->getRoom();

        if (!yield $this->adminStorage->isAdmin($room, $command->getUserId())) {
            return $this->chatClient->postReply($command, self::message('user_not_admin'));
        }

        if (!$command->hasParameters(2)) {
            return $this->chatClient->postReply($command, self::message('syntax'));
        }

        $cmd = $command->getParameter(1);

        if ($this->builtInCommandManager->hasRegisteredCommand($cmd)) {
            return $this->chatClient->postReply($command, self::message('command_built_in', $cmd));
        }

        if (!$this->pluginManager->isCommandMappedForRoom($room, $cmd)) {
            return $this->chatClient->postReply($command, self::message('command_not_mapped', $cmd));
        }

        yield $this->pluginManager->unmapCommandForRoom($room, $cmd);

        return $this->chatClient->postMessage($room, self::message('command_unmap_success', $cmd));
    }

    private /* async */ function remap(CommandMessage $command): \Generator
    {
        $room = $command->getRoom();

        if (!yield $this->adminStorage->isAdmin($room, $command->getUserId())) {
            return $this->chatClient->postReply($command, self::message('user_not_admin'));
        }

        if (!$command->hasParameters(3)) {
            return $this->chatClient->postReply($command, self::message('syntax'));
        }

        $cmd = $command->getParameter(1);
        $pluginName = $command->getParameter(2);
        $endpointName = $command->getParameter(3);

        if ($this->builtInCommandManager->hasRegisteredCommand($cmd)) {
            return $this->chatClient->postReply($command, self::message('command_built_in', $cmd));
        }

        if (!$this->pluginManager->isPluginRegistered($pluginName)) {
            return $this->chatClient->postReply($command, self::message('unknown_plugin', $pluginName));
        }

        $plugin = $this->pluginManager->getPluginByName($pluginName);

        if (!$this->pluginManager->isPluginEnabledForRoom($plugin, $room)) {
            return $this->chatClient->postReply($command, self::message('plugin_not_enabled', $plugin->getName()));
        }

        $endpoints = $this->pluginManager->getPluginCommandEndpoints($plugin);

        if ($endpointName === null) {
            $count = count($endpoints);

            if ($count > 1) {
                return $this->chatClient->postReply($command, self::message('multiple_endpoints', $pluginName, $count));
            }

            reset($endpoints);
            $endpointName = key($endpoints);
        } else {
            $validEndpoint = false;
            foreach ($endpoints as $name => $info) {
                if (strtolower($name) === strtolower($endpointName)) {
                    $validEndpoint = true;
                }
            }

            if (!$validEndpoint) {
                return $this->chatClient->postReply($command, self::message('unknown_endpoint', $endpointName, $pluginName));
            }
        }

        if ($this->pluginManager->isCommandMappedForRoom($room, $cmd)) {
            yield $this->pluginManager->unmapCommandForRoom($room, $cmd);
        }
        yield $this->pluginManager->mapCommandForRoom($room, $plugin, $endpointName, $cmd);

        return $this->chatClient->postMessage(
            $command,
            self::message('command_map_success', $cmd, $plugin->getName(), $endpointName)
        );
    }

    private /* async */ function clone(CommandMessage $command): \Generator
    {
        $room = $command->getRoom();

        if (!yield $this->adminStorage->isAdmin($room, $command->getUserId())) {
            return $this->chatClient->postReply($command, self::message('user_not_admin'));
        }

        if (!$command->hasParameters(2)) {
            return $this->chatClient->postReply($command, self::message('syntax'));
        }

        $newCmd = $command->getParameter(1);
        $oldCmd = $command->getParameter(2);

        if ($this->builtInCommandManager->hasRegisteredCommand($newCmd)) {
            return $this->chatClient->postReply($command, self::message('command_built_in', $newCmd));
        }

        if ($this->pluginManager->isCommandMappedForRoom($room, $newCmd)) {
            return $this->chatClient->postReply($command, self::message('command_already_mapped', $newCmd));
        }

        if ($this->builtInCommandManager->hasRegisteredCommand($oldCmd)) {
            return $this->chatClient->postReply($command, self::message('command_built_in', $oldCmd));
        }

        if (!$this->pluginManager->isCommandMappedForRoom($room, $oldCmd)) {
            return $this->chatClient->postReply($command, self::message('command_not_mapped', $oldCmd));
        }

        $mapping = $this->pluginManager->getMappedCommandsForRoom($room)[$oldCmd];

        if (!$this->pluginManager->isPluginEnabledForRoom($mapping['plugin_name'], $room)) {
            return $this->chatClient->postReply($command, self::message('plugin_not_enabled', $mapping['plugin_name']));
        }

        yield $this->pluginManager->mapCommandForRoom($room, $mapping['plugin_name'], $mapping['endpoint_name'], $newCmd);

        return $this->chatClient->postMessage(
            $command,
            self::message('command_map_success', $newCmd, $mapping['plugin_name'], $mapping['endpoint_name'])
        );
    }

    private function list(CommandMessage $command)
    {
        $room = $command->getRoom();

        $builtInCommands = $this->builtInCommandManager->getRegisteredCommandInfo();
        $pluginCommands = $this->pluginManager->getMappedCommandsForRoom($room);
        $aliases = yield $this->aliasStorage->getAll($room);

        ksort($builtInCommands);
        ksort($pluginCommands);
        ksort($aliases);

        $result = "Built-in commands (* indicates admin-only):";

        if (!$builtInCommands) {
            $result .= ' none';
        }

        foreach ($builtInCommands as $info) {
            $admin = $info->requiresAdminUser() ? '*' : '';
            $result .= "\n {$admin}{$info->getCommand()} - {$info->getDescription()}";
        }

        $result .= "\n\nPlugin commands currently mapped:";

        if (!$pluginCommands) {
            $result .= ' none';
        }

        foreach ($pluginCommands as $cmd => $info) {
            $result .= "\n {$cmd} - {$info['endpoint_description']} ({$info['plugin_name']} # {$info['endpoint_name']})";
        }

        $result .= "\n\nAliases currently mapped:";

        if (!$aliases) {
            $result .= ' none';
        }

        foreach ($aliases as $cmd => $alias) {
            $result .= "\n {$cmd} - '{$alias}'";
        }

        return $this->chatClient->postMessage($command, $result, PostFlags::FIXED_FONT);
    }

    private function showCommandHelp(CommandMessage $command): Promise
    {
        return $this->chatClient->postMessage($command, self::COMMAND_HELP_TEXT, PostFlags::FIXED_FONT);
    }

    public function __construct(
        PluginManager $pluginManager,
        BuiltInActionManager $builtInCommandManager,
        Client $chatClient,
        AdminStorage $adminStorage,
        CommandAliasStorage $aliasStorage
    ) {
        $this->pluginManager = $pluginManager;
        $this->builtInCommandManager = $builtInCommandManager;
        $this->chatClient = $chatClient;
        $this->adminStorage = $adminStorage;
        $this->aliasStorage = $aliasStorage;
    }

    /**
     * Handle a command message
     *
     * @param CommandMessage $command
     * @return Promise
     */
    public function handleCommand(CommandMessage $command): Promise
    {
        if ($command->getCommandName() === 'help') {
            return resolve($this->list($command));
        }

        try {
            switch ($command->getParameter(0)) {
                case 'help':  return $this->showCommandHelp($command);
                case 'list':  return resolve($this->list($command));
                case 'clone': return resolve($this->clone($command));
                case 'map':   return resolve($this->map($command));
                case 'remap': return resolve($this->remap($command));
                case 'unmap': return resolve($this->unmap($command));
            }
        } catch (\Throwable $e) {
            return $this->chatClient->postReply($command, self::message('unexpected_error', $e->getMessage()));
        }

        return $this->chatClient->postMessage($command, self::message('syntax'));
    }

    /**
     * Get a list of specific commands handled by this built-in
     *
     * @return BuiltInCommandInfo[]
     */
    public function getCommandInfo(): array
    {
        return [
            new BuiltInCommandInfo('command', "Manage command mappings. Use 'command help' for details."),
            new BuiltInCommandInfo('help', "Alias of 'command list'", BuiltInCommandInfo::ALLOW_UNAPPROVED_ROOM),
        ];
    }
}
