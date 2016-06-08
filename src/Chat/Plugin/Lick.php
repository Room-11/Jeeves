<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\AutoName;
use Room11\Jeeves\Chat\Plugin\Traits\CommandOnly;
use Room11\Jeeves\Chat\Plugin\Traits\Helpless;
use Room11\Jeeves\Chat\PluginCommandEndpoint;

class Lick implements Plugin
{
    use CommandOnly, AutoName, Helpless;

    const RESPONSES = [
        "Eeeeeeew",
        "That's sticky.",
        "At least buy me a drink first."
    ];

    private $chatClient;

    public function __construct(ChatClient $chatClient)
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
