<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;

class Say extends BasePlugin
{
    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function say(Command $command)
    {
        return $this->chatClient->postMessage($command, implode(' ', $command->getParameters()));
    }

    public function getDescription(): string
    {
        return 'Mindlessly parrots whatever crap you want';
    }

    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint('say', [$this, 'say'])
        ];
    }
}
