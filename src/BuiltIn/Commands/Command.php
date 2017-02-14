<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\PendingMessage;
use Room11\Jeeves\Chat\Client\PostFlags;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\CommandAlias as CommandAliasStorage;
use Room11\Jeeves\System\BuiltInActionManager;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\Jeeves\System\PluginManager;
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
        'syntax'                 => /** @lang text */ "    Syntax: command [map|remap] <command> <plugin> [<endpoint>]\n"
                                  . /** @lang text */ "            command unmap <command>\n"
                                  . /** @lang text */ "            command clone <new command> <existing command>\n"
                                  . /** @lang text */ "            command list",
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
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('user_not_admin'), $command)
            );
        }

        if (!$command->hasParameters(3)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('syntax'), $command)
            );
        }

        $cmd = $command->getParameter(1);
        $pluginName = $command->getParameter(2);
        $endpointName = $command->getParameter(3);

        if ($this->builtInCommandManager->hasRegisteredCommand($cmd)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('command_built_in', $cmd), $command)
            );
        }

        if ($this->pluginManager->isCommandMappedForRoom($room, $cmd)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('command_already_mapped', $cmd), $command)
            );
        }

        if (!$this->pluginManager->isPluginRegistered($pluginName)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('unknown_plugin', $pluginName), $command)
            );
        }

        $plugin = $this->pluginManager->getPluginByName($pluginName);

        if (!$this->pluginManager->isPluginEnabledForRoom($plugin, $room)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(
                    self::message('plugin_not_enabled', $plugin->getName()),
                    $command
                )
            );
        }

        $endpoints = $this->pluginManager->getPluginCommandEndpoints($plugin);

        if ($endpointName === null) {
            $count = count($endpoints);

            if ($count > 1) {
                return $this->chatClient->postReply(
                    $command,
                    new PendingMessage(
                        self::message('multiple_endpoints', $pluginName, $count),
                        $command
                    )
                );
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
                return $this->chatClient->postReply(
                    $command,
                    new PendingMessage(
                        self::message('unknown_endpoint', $endpointName, $pluginName),
                        $command
                    )
                );
            }
        }

        yield $this->pluginManager->mapCommandForRoom($room, $plugin, $endpointName, $cmd);

        return $this->chatClient->postMessage(
            $room,
            new PendingMessage(
                self::message('command_map_success', $cmd, $plugin->getName(), $endpointName),
                $command
            )
        );
    }

    private /* async */ function unmap(CommandMessage $command): \Generator
    {
        $room = $command->getRoom();

        if (!yield $this->adminStorage->isAdmin($room, $command->getUserId())) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('user_not_admin'), $command)
            );
        }

        if (!$command->hasParameters(2)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('syntax'), $command)
            );
        }

        $cmd = $command->getParameter(1);

        if ($this->builtInCommandManager->hasRegisteredCommand($cmd)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('command_built_in', $cmd), $command)
            );
        }

        if (!$this->pluginManager->isCommandMappedForRoom($room, $cmd)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('command_not_mapped', $cmd), $command)
            );
        }

        yield $this->pluginManager->unmapCommandForRoom($room, $cmd);

        return $this->chatClient->postMessage(
            $room,
            new PendingMessage(self::message('command_unmap_success', $cmd), $command)
        );
    }

    private /* async */ function remap(CommandMessage $command): \Generator
    {
        $room = $command->getRoom();

        if (!yield $this->adminStorage->isAdmin($room, $command->getUserId())) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('user_not_admin'), $command)
            );
        }

        if (!$command->hasParameters(3)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('syntax'), $command)
            );
        }

        $cmd = $command->getParameter(1);
        $pluginName = $command->getParameter(2);
        $endpointName = $command->getParameter(3);

        if ($this->builtInCommandManager->hasRegisteredCommand($cmd)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('command_built_in', $cmd), $command)
            );
        }

        if (!$this->pluginManager->isPluginRegistered($pluginName)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('unknown_plugin', $pluginName), $command)
            );
        }

        $plugin = $this->pluginManager->getPluginByName($pluginName);

        if (!$this->pluginManager->isPluginEnabledForRoom($plugin, $room)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(
                    self::message('plugin_not_enabled', $plugin->getName()),
                    $command
                )

            );
        }

        $endpoints = $this->pluginManager->getPluginCommandEndpoints($plugin);

        if ($endpointName === null) {
            $count = count($endpoints);

            if ($count > 1) {
                return $this->chatClient->postReply(
                    $command,
                    new PendingMessage(
                        self::message('multiple_endpoints', $pluginName, $count),
                        $command
                    )
                );
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
                return $this->chatClient->postReply(
                    $command,
                    new PendingMessage(
                        self::message('unknown_endpoint', $endpointName, $pluginName),
                        $command
                    )
                );
            }
        }

        if ($this->pluginManager->isCommandMappedForRoom($room, $cmd)) {
            yield $this->pluginManager->unmapCommandForRoom($room, $cmd);
        }
        yield $this->pluginManager->mapCommandForRoom($room, $plugin, $endpointName, $cmd);

        return $this->chatClient->postMessage(
            $room,
            new PendingMessage(
                self::message('command_map_success', $cmd, $plugin->getName(), $endpointName),
                $command
            )
        );
    }

    private /* async */ function clone(CommandMessage $command): \Generator
    {
        $room = $command->getRoom();

        if (!yield $this->adminStorage->isAdmin($room, $command->getUserId())) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('user_not_admin'), $command)
            );
        }

        if (!$command->hasParameters(2)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('syntax'), $command)
            );
        }

        $newCmd = $command->getParameter(1);
        $oldCmd = $command->getParameter(2);

        if ($this->builtInCommandManager->hasRegisteredCommand($newCmd)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('command_built_in', $newCmd), $command)
            );
        }

        if ($this->pluginManager->isCommandMappedForRoom($room, $newCmd)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('command_already_mapped', $newCmd), $command)
            );
        }

        if ($this->builtInCommandManager->hasRegisteredCommand($oldCmd)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('command_built_in', $oldCmd), $command)
            );
        }

        if (!$this->pluginManager->isCommandMappedForRoom($room, $oldCmd)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(self::message('command_not_mapped', $oldCmd), $command)
            );
        }

        $mapping = $this->pluginManager->getMappedCommandsForRoom($room)[$oldCmd];

        if (!$this->pluginManager->isPluginEnabledForRoom($mapping['plugin_name'], $room)) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(
                    self::message('plugin_not_enabled', $mapping['plugin_name']),
                    $command
                )
            );
        }

        yield $this->pluginManager->mapCommandForRoom($room, $mapping['plugin_name'], $mapping['endpoint_name'], $newCmd);

        return $this->chatClient->postMessage(
            $room,
            new PendingMessage(
                self::message('command_map_success', $newCmd, $mapping['plugin_name'], $mapping['endpoint_name']),
                $command
            )
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
            $admin = $info->isAdminOnly() ? '*' : '';
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

        return $this->chatClient->postMessage(
            $room,
            new PendingMessage($result, $command),
            PostFlags::FIXED_FONT
        );
    }

    private function showCommandHelp(CommandMessage $command): Promise
    {
        return $this->chatClient->postMessage(
            $command->getRoom(),
            new PendingMessage(self::COMMAND_HELP_TEXT, $command),
            PostFlags::FIXED_FONT
        );
    }

    public function __construct(
        PluginManager $pluginManager,
        BuiltInActionManager $builtInCommandManager,
        ChatClient $chatClient,
        AdminStorage $adminStorage,
        CommandAliasStorage $aliasStorage
    ) {
        $this->pluginManager = $pluginManager;
        $this->builtInCommandManager = $builtInCommandManager;
        $this->chatClient = $chatClient;
        $this->adminStorage = $adminStorage;
        $this->aliasStorage = $aliasStorage;
    }

    private function execute(CommandMessage $command)
    {
        if ($command->getCommandName() === 'help') {
            return yield from $this->list($command);
        }

        if (!yield $command->getRoom()->isApproved()) {
            return null;
        }

        try {
            switch ($command->getParameter(0)) {
                case 'help':  return $this->showCommandHelp($command);
                case 'list':  return yield from $this->list($command);
                case 'clone': return yield from $this->clone($command);
                case 'map':   return yield from $this->map($command);
                case 'remap': return yield from $this->remap($command);
                case 'unmap': return yield from $this->unmap($command);
            }
        } catch (\Throwable $e) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(
                    self::message('unexpected_error', $e->getMessage()),
                    $command
                )
            );
        }

        return $this->chatClient->postMessage(
            $command->getRoom(),
            new PendingMessage(self::message('syntax'), $command)
        );
    }

    /**
     * Handle a command message
     *
     * @param CommandMessage $command
     * @return Promise
     */
    public function handleCommand(CommandMessage $command): Promise
    {
        return resolve($this->execute($command));
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
            new BuiltInCommandInfo('help', "Alias of 'command list'"),
        ];
    }
}
