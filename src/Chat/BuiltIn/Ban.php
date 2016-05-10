<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\BuiltIn;

use Room11\Jeeves\Chat\BuiltInCommand;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as BanStorage;

class Ban implements BuiltInCommand
{
    private $chatClient;

    private $admin;

    private $storage;

    public function __construct(ChatClient $chatClient, AdminStorage $admin, BanStorage $storage) {
        $this->chatClient = $chatClient;
        $this->admin      = $admin;
        $this->storage    = $storage;
    }

    private function execute(CommandMessage $command): \Generator {
        if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
            yield from $this->chatClient->postReply(
                $command, "I'm sorry Dave, I'm afraid I can't do that"
            );

            return;
        }

        if ($command->getCommandName() === "ban" && $command->getParameter(0) === 'list') {
            yield from $this->list($command);
        } elseif ($command->getCommandName() === "ban") {
            if (!$command->hasParameters(2)) {
                yield from $this->chatClient->postReply(
                    $command, "Ban length must be specified"
                );

                return;
            }

            yield from $this->add($command, (int)$command->getParameter(0), $command->getParameter(1));
        } elseif ($command->getCommandName() === "unban") {
            yield from $this->remove($command, (int)$command->getParameter(0));
        }
    }

    private function list(CommandMessage $command): \Generator
    {
        $bans = yield $this->storage->getAll($command->getRoom());

        if (!$bans) {
            yield from $this->chatClient->postMessage($command->getRoom(), "No users are currently on the naughty list.");
            return;
        }

        $list = implode(", ", array_map(function($expiration, $userId) {
            return sprintf("%s (%s)", $userId, $expiration);
        }, $bans, array_keys($bans)));

        yield from $this->chatClient->postMessage($command->getRoom(), $list);
    }

    private function add(CommandMessage $command, int $userId, string $duration): \Generator {
        yield $this->storage->add($command->getRoom(), $userId, $duration);

        yield from $this->chatClient->postMessage($command->getRoom(), "User is banned.");
    }

    private function remove(CommandMessage $command, int $userId): \Generator {
        yield $this->storage->remove($command->getRoom(), $userId);

        yield from $this->chatClient->postMessage($command->getRoom(), "User is unbanned.");
    }

    /**
     * Handle a command message
     *
     * @param CommandMessage $command
     * @return \Generator
     */
    public function handleCommand(CommandMessage $command): \Generator
    {
        if (!$command->hasParameters()) {
            return;
        }

        yield from $this->execute($command);
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getCommandNames(): array
    {
        return ["ban", "unban"];
    }
}
