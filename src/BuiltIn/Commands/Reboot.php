<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use function Amp\resolve;
use Room11\Jeeves\Chat\Command as CommandMessage;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\StackChat\Client\Client as ChatClient;

class Reboot implements BuiltInCommand
{
    private $chatClient;

    private $adminStorage;

    public function __construct(ChatClient $chatClient, AdminStorage $adminStorage)
    {
        $this->chatClient   = $chatClient;
        $this->adminStorage = $adminStorage;
    }

    public function handleCommand(CommandMessage $command): Promise
    {
        return resolve(function() use ($command) {
            if (!yield $this->adminStorage->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            }

            $message = yield $this->chatClient->postMessage($command, 'Restarting in 10 seconds.');

            \Amp\once(function() use ($message) {
                yield $this->chatClient->editMessage($message, 'Restarting now! o/');

                exit;
            }, 10000);
        });
    }

    public function getCommandInfo(): array
    {
        return [
            new BuiltInCommandInfo('reboot', 'Restarts the bot.'),
        ];
    }
}
