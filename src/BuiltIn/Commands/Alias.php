<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Command as CommandMessage;
use Room11\Jeeves\Chat\CommandFactory;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\CommandAlias as CommandAliasStorage;
use Room11\Jeeves\System\BuiltInActionManager;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\Jeeves\System\PluginManager;
use Room11\StackChat\Client\Client as ChatClient;

class Alias implements BuiltInCommand
{
    private $chatClient;
    private $aliasStorage;
    private $adminStorage;
    private $builtInCommandManager;
    private $pluginManager;

    public function __construct(
        ChatClient $chatClient,
        CommandAliasStorage $aliasStorage,
        AdminStorage $adminStorage,
        BuiltInActionManager $builtInCommandManager,
        PluginManager $pluginManager
    ) {
        $this->chatClient = $chatClient;
        $this->aliasStorage = $aliasStorage;
        $this->adminStorage = $adminStorage;
        $this->builtInCommandManager = $builtInCommandManager;
        $this->pluginManager = $pluginManager;
    }

    private function formatCommand(string $commandText)
    {
        return CommandFactory::INVOKER . $commandText;
    }

    private function parseAliasCommandText(CommandMessage $command)
    {
        $text = yield $this->chatClient->getMessageText($command->getRoom(), $command->getId());

        $markdown = trim(substr($text, strlen($command->getCommandName()) + strlen(CommandFactory::INVOKER)));

        list($aliasCommand, $mapping) = preg_split('/\s+/', $markdown, 2, PREG_SPLIT_NO_EMPTY);

        return [strtolower($aliasCommand), $mapping];
    }

    private function addAlias(CommandMessage $command)
    {
        $room = $command->getRoom();

        list($aliasCommand, $mapping) = yield from $this->parseAliasCommandText($command);

        if ($this->builtInCommandManager->hasRegisteredCommand($aliasCommand)) {
            return $this->chatClient->postReply(
                $command,
                "Command '" . $this->formatCommand($aliasCommand) . "' is built in and cannot be altered"
            );
        }

        if ($this->pluginManager->isCommandMappedForRoom($room, $aliasCommand)) {
            return $this->chatClient->postReply(
                $command,
                "Command '" . $this->formatCommand($aliasCommand) . "' is already mapped." .
                " Use `" . $this->formatCommand('command list') . "` to display the currently mapped commands."
            );
        }

        if (yield $this->aliasStorage->exists($room, $aliasCommand)) {
            return $this->chatClient->postReply(
                $command,
                "Alias '" . $this->formatCommand($aliasCommand) . "' already exists."
            );
        }

        yield $this->aliasStorage->set($room, $aliasCommand, $mapping);

        return $this->chatClient->postMessage(
            $command,
            "Command '" . $this->formatCommand($aliasCommand) . "' aliased to '" . $this->formatCommand($mapping) . "'"
        );
    }

    private function removeAlias(CommandMessage $command)
    {
        $room = $command->getRoom();

        $aliasCommand = strtolower($command->getParameter(0));

        if (!yield $this->aliasStorage->exists($room, $aliasCommand)) {
            return $this->chatClient->postReply(
                $command,
                "Alias '" . $this->formatCommand($aliasCommand) . "' is not currently mapped"
            );
        }

        yield $this->aliasStorage->remove($room, $aliasCommand);

        return $this->chatClient->postMessage(
            $command,
            "Alias '" . $this->formatCommand($aliasCommand) . "' removed"
        );
    }

    private function replaceAlias(CommandMessage $command)
    {
        $room = $command->getRoom();

        list($aliasCommand, $mapping) = yield from $this->parseAliasCommandText($command);

        if (!yield $this->aliasStorage->exists($room, $aliasCommand)) {
            return $this->chatClient->postReply(
                $command,
                "Alias '" . $this->formatCommand($aliasCommand) . "' is not currently mapped"
            );
        }

        yield $this->aliasStorage->set($room, $aliasCommand, $mapping);

        return $this->chatClient->postMessage(
            $command,
            "Command '" . $this->formatCommand($aliasCommand) . "' aliased to '" . $this->formatCommand($mapping) . "'"
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
        return \Amp\resolve(function() use($command) {
            if (!yield $this->adminStorage->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            }

            switch ($command->getCommandName()) {
                case 'alias':
                    return yield from $this->addAlias($command);
                case 'unalias':
                    return yield from $this->removeAlias($command);
                case 'realias':
                    return yield from $this->replaceAlias($command);
            }

            return null;
        });
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return BuiltInCommandInfo[]
     */
    public function getCommandInfo(): array
    {
        return [
            new BuiltInCommandInfo('alias', 'Add a bash-style alias', BuiltInCommandInfo::REQUIRE_ADMIN_USER),
            new BuiltInCommandInfo('unalias', 'Remove a bash-style alias', BuiltInCommandInfo::REQUIRE_ADMIN_USER),
            new BuiltInCommandInfo('realias', 'Replace a bash-style alias', BuiltInCommandInfo::REQUIRE_ADMIN_USER),
        ];
    }
}
