<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as Storage;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;

class Ban implements Plugin
{
    const COMMANDS = ["ban", "unban"];

    private $chatClient;

    private $admin;

    private $storage;

    public function __construct(ChatClient $chatClient, AdminStorage $admin, Storage $storage) {
        $this->chatClient = $chatClient;
        $this->admin      = $admin;
        $this->storage    = $storage;
    }

    public function handle(Message $message): \Generator {
        if (!$this->validMessage($message)) {
            return;
        }

        /** @var Command $message */
        yield from $this->execute($message);
    }

    private function validMessage(Message $message): bool {
        return $message instanceof Command
            && in_array($message->getCommand(), self::COMMANDS, true)
            && $message->getParameters();
    }

    private function execute(Command $message): \Generator {
        if (!yield from $this->admin->isAdmin($message->getMessage()->getUserId())) {
            yield from $this->chatClient->postMessage(
                sprintf(":%d I'm sorry Dave, I'm afraid I can't do that", $message->getOrigin())
            );

            return;
        }

        if ($message->getCommand() === "ban" && $message->getParameters()[0] === 'list') {
            yield from $this->list();
        } elseif ($message->getCommand() === "ban") {
            yield from $this->add((int)$message->getParameters()[0], $message->getParameters()[1]);
        } elseif ($message->getCommand() === "unban") {
            yield from $this->remove((int) $message->getParameters()[0]);
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
}
