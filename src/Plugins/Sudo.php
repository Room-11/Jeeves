<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\System\PluginCommandEndpoint;

class Sudo extends BasePlugin
{
    private $chatClient;

    public function __construct(ChatClient $chatClient)
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
