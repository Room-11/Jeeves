<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\System\BuiltInCommand;

class Leave implements BuiltInCommand
{
    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function handleCommand(CommandMessage $command): Promise
    {
        // TODO: Implement handleCommand() method.
    }

    public function getCommandNames(): array
    {
        return ['leave'];
    }
}
