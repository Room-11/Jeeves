<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\System\BuiltInCommand;
use function Amp\resolve;
use Room11\Jeeves\System\BuiltInCommandInfo;

class Ban implements BuiltInCommand
{
    private $chatClient;
    private $adminStorage;
    private $banStorage;

    public function __construct(ChatClient $chatClient, AdminStorage $adminStorage, BanStorage $banStorage)
    {
        $this->chatClient   = $chatClient;
        $this->adminStorage = $adminStorage;
        $this->banStorage   = $banStorage;
    }

    private function execute(CommandMessage $command)
    {
        if (!yield $command->getRoom()->isApproved()) {
            return null;
        }

        if (!yield $this->adminStorage->isAdmin($command->getRoom(), $command->getUserId())) {
            return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
        }

        if ($command->getCommandName() === "ban" && $command->getParameter(0) === 'list') {
            yield from $this->list($command);
        } else if ($command->getCommandName() === "ban") {
            if (!$command->hasParameters(2)) {
                return $this->chatClient->postReply($command, "Ban length must be specified");
            }

            yield from $this->add($command, (int)$command->getParameter(0), $command->getParameter(1));
        } else if ($command->getCommandName() === "unban") {
            yield from $this->remove($command, (int)$command->getParameter(0));
        }
    }

    private function list(CommandMessage $command): \Generator
    {
        $bans = yield $this->banStorage->getAll($command->getRoom());

        if (!$bans) {
            yield $this->chatClient->postMessage($command->getRoom(), "No users are currently on the naughty list.");
            return;
        }

        $list = implode(", ", array_map(function($expiration, $userId) {
            return sprintf("%s (%s)", $userId, $expiration);
        }, $bans, array_keys($bans)));

        yield $this->chatClient->postMessage($command->getRoom(), $list);
    }

    private function add(CommandMessage $command, int $userId, string $duration): \Generator {
        yield $this->banStorage->add($command->getRoom(), $userId, $duration);

        yield $this->chatClient->postMessage($command->getRoom(), "User is banned.");
    }

    private function remove(CommandMessage $command, int $userId): \Generator {
        yield $this->banStorage->remove($command->getRoom(), $userId);

        yield $this->chatClient->postMessage($command->getRoom(), "User is unbanned.");
    }

    /**
     * Handle a command message
     *
     * @param CommandMessage $command
     * @return Promise
     */
    public function handleCommand(CommandMessage $command): Promise
    {
        return $command->hasParameters()
            ? resolve($this->execute($command))
            : new Success();
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return BuiltInCommandInfo[]
     */
    public function getCommandInfo(): array
    {
        return [
            new BuiltInCommandInfo('ban', 'Ban a user from interacting with the bot for a specified period of time', true),
            new BuiltInCommandInfo('unban', "Remove a user's ban status", true),
        ];
    }
}
