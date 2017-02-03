<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\PostFlags;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\System\BuiltInCommand;
use const Room11\Jeeves\PROCESS_START_TIME;
use function Amp\resolve;
use function Room11\Jeeves\dateinterval_to_string;
use Room11\Jeeves\System\BuiltInCommandInfo;

class Uptime implements BuiltInCommand
{
    private $chatClient;

    private $startTime;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;

        $this->startTime = new \DateTimeImmutable('@' . PROCESS_START_TIME);
    }

    private function execute(CommandMessage $command)
    {
        if (!yield $command->getRoom()->isApproved()) {
            return null;
        }

        $lastAccident = dateinterval_to_string((new \DateTime)->diff($this->startTime));
        $since = $this->startTime->format('Y-m-d H:i:s');
        
        $lastAccidentMessage = " [" . $lastAccident . "] without an accident ";
        $sinceMessage = " since [" . $since . "] ";
        
        $lineLength = max(strlen($lastAccidentMessage), strlen($sinceMessage));
        
        $message  = "╔" . str_repeat("═", $lineLength) . "╗\n";
        $message .= "║" . str_pad($lastAccidentMessage, $lineLength, " ", STR_PAD_BOTH) . "║\n";
        $message .= "║" . str_pad($sinceMessage, $lineLength, " ", STR_PAD_BOTH) . "║\n";
        $message .= "╚" . str_repeat("═", $lineLength) . "╝\n";
        
        return $this->chatClient->postMessage($command->getRoom(), $message, PostFlags::FIXED_FONT);
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
     * @return BuiltInCommandInfo[]
     */
    public function getCommandInfo(): array
    {
        return [
            new BuiltInCommandInfo('uptime', "Display how long the bot has been running."),
        ];
    }
}
