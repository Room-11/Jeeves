<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client;

class Lick extends BasePlugin
{
    private const RESPONSES = [
        "Eeeeeeew",
        "That's sticky.",
        "At least buy me a drink first."
    ];

    private $chatClient;

    public function __construct(Client $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    private function getRandomReply(): string
    {
        return self::RESPONSES[random_int(0, (count(self::RESPONSES) - 1))];
    }

    public function lick(Command $command): Promise
    {
        return $this->chatClient->postReply($command, $this->getRandomReply());
    }

    public function getDescription(): string
    {
        return 'Implements the Lickable interface';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Lick', [$this, 'lick'], 'lick')];
    }
}
