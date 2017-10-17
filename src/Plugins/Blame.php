<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;

class Blame extends BasePlugin
{
    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function blame(Command $command): Promise
    {
        if (!$command->hasParameters()) {
            return $this->chatClient->postMessage($command, 'https://img.shields.io/badge/peehaas-FAULT-red.svg');
        }

        $target = implode(' ', $command->getParameters());
        $target = str_replace('-', '_', $target);

        if (substr($target, -1) !== 's') {
            $target .= 's';
        }

        return $this->chatClient->postMessage($command, 'https://img.shields.io/badge/' . $target . '-FAULT-red.svg');
    }

    public function getName(): string
    {
        return 'blame';
    }

    public function getDescription(): string
    {
        return 'Blames somebody or something';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Blame', [$this, 'blame'], 'blame')];
    }
}
