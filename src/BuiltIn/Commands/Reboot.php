<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use function Amp\resolve;
use Room11\Jeeves\Chat\Command as CommandMessage;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\StackChat\Client\Client as ChatClient;

class Reboot implements BuiltInCommand
{
    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function handleCommand(CommandMessage $command): Promise
    {
        return resolve(function() use ($command) {
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
