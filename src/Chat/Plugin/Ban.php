<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as Storage;
use Room11\Jeeves\Chat\Command\Command;

class Ban implements Plugin
{
    use CommandOnlyPlugin;

    private $chatClient;

    private $admin;

    private $storage;

    public function __construct(ChatClient $chatClient, AdminStorage $admin, Storage $storage) {
        $this->chatClient = $chatClient;
        $this->admin      = $admin;
        $this->storage    = $storage;
    }

    private function execute(Command $command): \Generator {
        $message = $command->getMessage();
        if (!yield from $this->admin->isAdmin($message->getUserId())) {
            yield from $this->chatClient->postReply(
                $message, "I'm sorry Dave, I'm afraid I can't do that"
            );

            return;
        }

        if ($command->getCommand() === "ban" && $command->getParameters()[0] === 'list') {
            yield from $this->list();
        } elseif ($command->getCommand() === "ban") {
            yield from $this->add((int)$command->getParameters()[0], $command->getParameters()[1]);
        } elseif ($command->getCommand() === "unban") {
            yield from $this->remove((int) $command->getParameters()[0]);
        }
    }

    private function list(): \Generator
    {
        $bans = yield from $this->storage->getAll();

        if (!$bans) {
            yield from $this->chatClient->postMessage("No users are currently on the naughty list.");
            return;
        }

        $list = implode(", ", array_map(function($expiration, $userId) {
            return sprintf("%s (%s)", $userId, $expiration);
        }, $bans, array_keys($bans)));

        yield from $this->chatClient->postMessage($list);
    }

    private function add(int $userId, string $duration): \Generator {
        yield from $this->storage->add($userId, $duration);

        yield from $this->chatClient->postMessage("User is banned.");
    }

    private function remove(int $userId): \Generator {
        yield from $this->storage->remove($userId);

        yield from $this->chatClient->postMessage("User is unbanned.");
    }

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator
    {
        if (!$command->getParameters()) {
            return;
        }

        yield from $this->execute($command);
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getHandledCommands(): array
    {
        return ["ban", "unban"];
    }
}
