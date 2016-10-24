<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\System\BuiltInCommand;
use const Room11\Jeeves\PROCESS_START_TIME;
use function Amp\resolve;
use function Room11\Jeeves\dateinterval_to_string;

class Uptime implements BuiltInCommand
{
    private $chatClient;

    private $startTime;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;

        $this->startTime = new \DateTimeImmutable('@' . PROCESS_START_TIME);
    }

    private function execute(CommandMessage $command): \Generator
    {
        if (!yield $command->getRoom()->isApproved()) {
            return;
        }

        return $this->chatClient->postReply($command, sprintf(
            'I have been running for %s, since %s',
            dateinterval_to_string((new \DateTime)->diff($this->startTime)),
            $this->startTime->format('Y-m-d H:i:s')
        ));
    }

    /**
     * Handle a command message
     *
     * @param CommandMessage $command
     * @return Promise
     */
    public function handleCommand(CommandMessage $command): Promise
    {
        return resolve($this->execute($command));
    }

    /**
     * Get a list of specific commands handled by this built-in
     *
     * @return string[]
     */
    public function getCommandNames(): array
    {
        return ['uptime'];
    }
}
