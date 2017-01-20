<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\System\CommandAliasMap;
use SebastianBergmann\Version as SebastianVersion;
use const Room11\Jeeves\APP_BASE;
use function Amp\resolve;

class Alias implements BuiltInCommand
{
    private $chatClient;
    private $adminStorage;
    private $aliasMap;

    public function __construct(ChatClient $chatClient, CommandAliasMap $aliasMap, AdminStorage $adminStorage)
    {
        $this->chatClient = $chatClient;
        $this->aliasMap = $aliasMap;
        $this->adminStorage = $adminStorage;
    }

    private function addAlias(CommandMessage $command): Promise
    {
        $aliasCommand = $command->getParameter(0);
        $mapping = implode(' ', $command->getParameters(1));

        $this->aliasMap->addMapping($command->getRoom(), $aliasCommand, $mapping);

        return $this->chatClient->postMessage($command->getRoom(), "Command '!!{$aliasCommand}' aliased to '!!{$mapping}'");
    }

    private function removeAlias(CommandMessage $command): \Generator
    {
        $aliasCommand = $command->getParameter(0);

        if (!$this->aliasMap->mappingExists($command->getRoom(), $aliasCommand)) {
            return $this->chatClient->postMessage($command->getRoom(), "Alias '!!{$aliasCommand}' is not currently mapped");
        }

        $version = (new SebastianVersion(VERSION, APP_BASE))->getVersion();

        $messageText = preg_replace_callback('@v([0-9.]+)(?:-\d+-g([0-9a-f]+))?@', function($match) {
            return sprintf(
                "[%s](%s)",
                $match[0],
                empty($match[2])
                    ? "https://github.com/Room-11/Jeeves/tree/v" . $match[1]
                    : "https://github.com/Room-11/Jeeves/commit/" . $match[2]
            );
        }, $version);

        yield $this->chatClient->postMessage($command->getRoom(), $messageText);
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
                ? $this->addAlias($command)
                : $this->removeAlias($command);
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
