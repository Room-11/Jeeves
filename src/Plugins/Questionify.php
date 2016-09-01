<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\MessageResolver;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\System\PluginCommandEndpoint;

class Questionify extends BasePlugin
{
    private $chatClient;
    private $messageResolver;

    public function __construct(ChatClient $chatClient, MessageResolver $messageResolver)
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

        return $this->chatClient->postMessage($command->getRoom(), rtrim($text) . '?');
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
