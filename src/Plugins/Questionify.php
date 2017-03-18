<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Room11\Jeeves\Chat\Command as CommandMessage;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client;
use Room11\StackChat\Client\MessageResolver;

class Questionify extends BasePlugin
{
    private $chatClient;
    private $messageResolver;

    public function __construct(Client $chatClient, MessageResolver $messageResolver)
    {
        $this->chatClient = $chatClient;
        $this->messageResolver = $messageResolver;
    }

    public function questionify(CommandMessage $command)
    {
        $text = yield $this->messageResolver->resolveMessageText($command->getRoom(), $command->getText());

        if (preg_match('/\?\s*$/', $text)) {
            return $this->chatClient->postReply($command, 'That\'s already a question');
        }

        return $this->chatClient->postMessage($command, rtrim($text) . '?');
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('questionify', [$this, 'questionify'], 'questionify')];
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Anything can be a question if you want it to be.';
    }
}
