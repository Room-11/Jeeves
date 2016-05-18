<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\BuiltIn;

use Amp\Promise;
use Room11\Jeeves\Chat\BuiltInCommand;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Chat\PluginManager;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use function Amp\resolve;

class Command implements BuiltInCommand
{
    private $pluginManager;
    private $chatClient;
    private $adminStorage;

    private function showSyntax(CommandMessage $command): Promise
    {
        return $this->chatClient->postReply($command, "Syntax: command [map|unmap|list] [command] [plugin] [endpoint]");
    }

    private function map(CommandMessage $command): Promise
    {
        return resolve(function() use($command) {
            if (!yield $this->adminStorage->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            }

            if (!$command->hasParameters(3)) {
                return $this->showSyntax($command);
            }

            $cmd = $command->getParameter(1);

            if ($this->pluginManager->isCommandMappedForRoom($command->getRoom(), $cmd)) {
                return $this->chatClient->postReply($command, "Command '$cmd' is already mapped in this room");
            }

            if (!$this->pluginManager->isPluginRegistered($command->getParameter(2))) {
                return $this->chatClient->postReply($command, "Invalid plugin name: " . $command->getParameter(2));
            }

            $plugin = $this->pluginManager->getPluginByName($command->getParameter(2));
            $endpoints = $this->pluginManager->getPluginCommandEndpoints($plugin);

            $endpoint = $command->getParameter(3);

            if ($endpoint === null) {
                if (count($endpoints) > 1) {
                    return $this->chatClient->postReply(
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
                    return $this->chatClient->postReply($command, "Invalid endpoint name: {$endpoint}");
                }
            }

            yield $this->pluginManager->mapCommandForRoom($command->getRoom(), $plugin, $endpoint, $cmd);

            return $this->chatClient->postMessage(
                $command->getRoom(), "Command '{$cmd}' mapped to {$plugin->getName()} # {$endpoint}"
            );
        });
    }

    private function unmap(CommandMessage $command): Promise
    {
        return resolve(function() use($command) {
            if (!yield $this->adminStorage->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            }

            if (!$command->hasParameters(2)) {
                return $this->showSyntax($command);
            }

            $cmd = $command->getParameter(1);

            if (!$this->pluginManager->isCommandMappedForRoom($command->getRoom(), $cmd)) {
                return $this->chatClient->postReply($command, "Command '$cmd' is not currently mapped in this room");
            }

            $this->pluginManager->unmapCommandForRoom($command->getRoom(), $cmd);

            return $this->chatClient->postMessage($command->getRoom(), "Mapping for command '{$cmd}' removed");
        });
    }

    private function list(CommandMessage $command): Promise
    {
        $mappings = $this->pluginManager->getMappedCommandsForRoom($command->getRoom());

        if (!$mappings) {
            return $this->chatClient->postMessage($command->getRoom(), "No commands are currently mapped");
        }

        ksort($mappings);

        $result = "Commands currently mapped:";

        foreach ($mappings as $cmd => $info) {
            $result .= "\n {$cmd} - {$info['endpoint_description']} ({$info['plugin_name']} # {$info['endpoint_name']})";
        }

        return $this->chatClient->postMessage($command->getRoom(), $result, true);
    }

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
     * @return Promise
     */
    public function handleCommand(CommandMessage $command): Promise
    {
        switch ($command->getParameter(0)) {
            case 'map':   return $this->map($command);
            case 'unmap': return $this->unmap($command);
            case 'list':  return $this->list($command);
        }

        return $this->showSyntax($command);
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
