<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;

class Lick implements Plugin
{
    use CommandOnlyPlugin;

    const RESPONSES = [
        "Eeeeeeew",
        "That's sticky.",
        "At least buy me a drink first."
    ];

    private $chatClient;

    public function __construct(ChatClient $chatClient) {
        $this->chatClient = $chatClient;
    }

    private function getResult(Command $command): \Generator {
        yield from $this->chatClient->postReply($command->getMessage(), $this->getRandomReply());
    }

    private function getRandomReply(): string
    {
        return self::RESPONSES[random_int(0, (count(self::RESPONSES) - 1))];
    }

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator
    {
        yield from $this->getResult($command);
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getHandledCommands(): array
    {
        return ['lick'];
    }
}
