<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\CommandAlias as CommandAliasStorage;
use Room11\Jeeves\System\BuiltInCommand;
use function Amp\resolve;

class Alias implements BuiltInCommand
{
    private $chatClient;
    private $adminStorage;
    private $aliasMap;

    public function __construct(ChatClient $chatClient, CommandAliasStorage $aliasMap, AdminStorage $adminStorage)
    {
        $this->chatClient = $chatClient;
        $this->aliasMap = $aliasMap;
        $this->adminStorage = $adminStorage;
    }

    private function addAlias(CommandMessage $command)
    {
        $aliasCommand = $command->getParameter(0);
        $mapping = implode(' ', $command->getParameters(1));

        yield $this->aliasMap->add($command->getRoom(), $aliasCommand, $mapping);

        return $this->chatClient->postMessage($command->getRoom(), "Command '!!{$aliasCommand}' aliased to '!!{$mapping}'");
    }

    private function removeAlias(CommandMessage $command): \Generator
    {
        $aliasCommand = $command->getParameter(0);

        if (!yield $this->aliasMap->exists($command->getRoom(), $aliasCommand)) {
            return $this->chatClient->postMessage($command->getRoom(), "Alias '!!{$aliasCommand}' is not currently mapped");
        }

        yield $this->aliasMap->remove($command->getRoom(), $aliasCommand);

        return $this->chatClient->postMessage($command->getRoom(), "Alias '!!{$aliasCommand}' removed");
    }

    /**
     * Handle a command message
     *
     * @param CommandMessage $command
     * @return Promise
     */
    public function handleCommand(CommandMessage $command): Promise
    {
        return resolve(function() use($command) {
            if (!yield $command->getRoom()->isApproved()) {
                return null;
            }

            if (!yield $this->adminStorage->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            }

            return $command->getCommandName() === 'alias'
                ? yield from $this->addAlias($command)
                : yield from $this->removeAlias($command);
        });
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getCommandNames(): array
    {
        return ['alias', 'unalias'];
    }
}
