<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\BuiltIn;

use Room11\Jeeves\Chat\BuiltInCommand;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Chat\PluginManager;

class Command implements BuiltInCommand
{
    private $pluginManager;
    private $chatClient;

    private function showSyntax(CommandMessage $command): \Generator
    {
        yield from $this->chatClient->postReply($command, "Syntax: command [map|unmap|list] [command] [plugin] [endpoint]");
    }

    private function map(CommandMessage $command): \Generator
    {
        if (!$command->hasParameters(3)) {
            yield from $this->showSyntax($command);
            return;
        }

        $cmd = $command->getParameter(1);

        if ($this->pluginManager->isCommandMappedForRoom($command->getRoom(), $cmd)) {
            yield from $this->chatClient->postReply($command, "Command '$cmd' is already mapped in this room");
            return;
        }

        if (!$this->pluginManager->isPluginRegistered($command->getParameter(2))) {
            yield from $this->chatClient->postReply($command, "Invalid plugin name: " . $command->getParameter(2));
            return;
        }

        $plugin = $this->pluginManager->getPluginByName($command->getParameter(2));
        $endpoints = $this->pluginManager->getPluginCommandEndpoints($plugin);

        $endpoint = $command->getParameter(3);

        if ($endpoint === null) {
            if (count($endpoints) > 1) {
                yield from $this->chatClient->postReply(
                    $command,
                    "Plugin provides multiple endpoints, you must specify the endpoint to which the command maps"
                );
            }

            reset($endpoints);
            $endpoint = key($endpoints);
        } else {
            $validEndpoint = false;
            foreach ($endpoints as $name => $info) {
                if (strtolower($name) === strtolower($endpoint)) {
                    $validEndpoint = true;
                }
            }

            if (!$validEndpoint) {
                yield from $this->chatClient->postReply($command, "Invalid endpoint name: {$endpoint}");
                return;
            }
        }

        $this->pluginManager->mapCommandForRoom($command->getRoom(), $plugin, $endpoint, $cmd);

        yield from $this->chatClient->postMessage(
            $command->getRoom(), "Command '{$cmd}' mapped to {$plugin->getName()} # {$endpoint}"
        );
    }

    private function unmap(CommandMessage $command): \Generator
    {
        if (!$command->hasParameters(2)) {
            yield from $this->showSyntax($command);
            return;
        }

        $cmd = $command->getParameter(1);

        if (!$this->pluginManager->isCommandMappedForRoom($command->getRoom(), $cmd)) {
            yield from $this->chatClient->postReply($command, "Command '$cmd' is not currently mapped in this room");
            return;
        }

        $this->pluginManager->unmapCommandForRoom($command->getRoom(), $cmd);

        yield from $this->chatClient->postMessage(
            $command->getRoom(), "Mapping for command '{$cmd}' removed"
        );
    }

    private function list(CommandMessage $command): \Generator
    {
        $mappings = $this->pluginManager->getMappedCommandsForRoom($command->getRoom());

        if (!$mappings) {
            yield from $this->chatClient->postMessage($command->getRoom(), "No commands are currently mapped");
            return;
        }

        $result = "Commands currently mapped:";

        foreach ($mappings as $cmd => $info) {
            $result .= "\n {$cmd} - {$info['endpoint_description']} ({$info['plugin_name']} # {$info['endpoint_name']})";
        }

        yield from $this->chatClient->postMessage($command->getRoom(), $result, true);
    }

    public function __construct(PluginManager $pluginManager, ChatClient $chatClient)
    {
        $this->pluginManager = $pluginManager;
        $this->chatClient = $chatClient;
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
            case 'map':
                yield from $this->map($command);
                return;

            case 'unmap':
                yield from $this->unmap($command);
                return;

            case 'list':
                yield from $this->list($command);
                return;
        }

        yield from $this->showSyntax($command);
    }

    /**
     * Get a list of specific commands handled by this built-in
     *
     * @return string[]
     */
    public function getCommandNames(): array
    {
        return ['command'];
    }
}
