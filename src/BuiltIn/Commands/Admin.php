<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Artax\HttpClient;
use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\PendingMessage;
use Room11\Jeeves\Chat\Client\PostFlags;
use Room11\Jeeves\Chat\Entities\ChatUser;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\BuiltInCommand;
use function Amp\resolve;
use Room11\Jeeves\System\BuiltInCommandInfo;

class Admin implements BuiltInCommand
{
    private $chatClient;
    private $httpClient;
    private $storage;

    const COMMAND_HELP_TEXT =
        "Sub-commands (* indicates admin-only):"
        . "\n"
        . "\n help    - Display this message"
        . "\n list    - Display a list of the current admin users."
        . "\n *add    - Add a user to the admin list."
        . "\n            Syntax: admin add <user id>"
        . "\n *remove - Remove a user from the admin list."
        . "\n            Syntax: admin remove <user id>"
    ;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, AdminStorage $storage)
    {
        $this->chatClient = $chatClient;
        $this->storage    = $storage;
        $this->httpClient = $httpClient;
    }

    private function list(CommandMessage $command)
    {
        $admins = yield $this->storage->getAll($command->getRoom());

        if ($admins['owners'] === [] && $admins['admins'] === []) {
            return $this->chatClient->postMessage(
                $command->getRoom(),
                new PendingMessage('There are no registered admins', $command)
            );
        }

        $userIds = array_merge($admins['owners'], $admins['admins']);

        $users = /** @noinspection PhpStrictTypeCheckingInspection */
            yield $this->chatClient->getChatUsers($command->getRoom(), ...$userIds);
        usort($users, function (ChatUser $a, ChatUser $b) { return strcasecmp($a->getName(), $b->getName()); });

        $list = implode(', ', array_map(function(ChatUser $user) use($admins) {
            return in_array($user->getId(), $admins['owners'])
                ? '*' . $user->getName() . '*'
                : $user->getName();
        }, $users));

        return $this->chatClient->postMessage(
            $command->getRoom(),
            new PendingMessage($list, $command)
        );
    }

    private function add(CommandMessage $command, int $userId)
    {
        $admins = yield $this->storage->getAll($command->getRoom());

        if (in_array($userId, $admins['admins'])) {
            return $this->chatClient->postReply($command, 'User already on admin list.');
        }

        if (in_array($userId, $admins['owners'])) {
            return $this->chatClient->postReply($command, 'User is a room owner and has implicit admin rights.');
        }

        yield $this->storage->add($command->getRoom(), $userId);

        return $this->chatClient->postMessage(
            $command->getRoom(),
            new PendingMessage('User added to the admin list.', $command)
        );
    }

    private function remove(CommandMessage $command, int $userId)
    {
        $admins = yield $this->storage->getAll($command->getRoom());

        if (in_array($userId, $admins['owners'])) {
            return $this->chatClient->postReply($command, 'User is a room owner and has implicit admin rights.');
        }

        if (!in_array($userId, $admins['admins'])) {
            return $this->chatClient->postReply($command, 'User not currently on admin list.');
        }

        yield $this->storage->remove($command->getRoom(), $userId);

        return $this->chatClient->postMessage(
            $command->getRoom(),
            new PendingMessage('User removed from the admin list.', $command)
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

    private function execute(CommandMessage $command)
    {
        if (!yield $command->getRoom()->isApproved()) {
            return null;
        }

        if ($command->getParameter(0) === "help") {
            return $this->showCommandHelp($command);
        }

        if ($command->getParameter(0) === "list") {
            return yield from $this->list($command);
        }

        if (!yield $this->storage->isAdmin($command->getRoom(), $command->getUserId())) {
            return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
        }

        switch ($command->getParameter(0)) {
            case 'add':    return yield from $this->add($command, (int)$command->getParameter(1));
            case 'remove': return yield from $this->remove($command, (int)$command->getParameter(1));
        }

        return null;
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
     * Get a list of specific commands handled by this plugin
     *
     * @return BuiltInCommandInfo[]
     */
    public function getCommandInfo(): array
    {
        return [
            new BuiltInCommandInfo('admin', "Manage the bot's admin list. Use 'admin help' for details.")
        ];
    }
}
