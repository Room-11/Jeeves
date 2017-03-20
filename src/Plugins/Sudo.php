<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Room11\Jeeves\Chat\Command as CommandMessage;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client;

class Sudo extends BasePlugin
{
    private $chatClient;

    public function __construct(Client $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function sudo(CommandMessage $command)
    {
        return $this->chatClient->postReply($command, 'absolutely not, now make me a sandwich.');
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('sudo', [$this, 'sudo'], 'sudo')];
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Makes Jeeves do things with a higher permission level';
    }
}
