<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Command as CommandMessage;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\StackChat\Client\Client;
use Room11\StackChat\Client\PostFlags;
use const Room11\Jeeves\PROCESS_START_TIME;
use function Room11\Jeeves\dateinterval_to_string;

class Uptime implements BuiltInCommand
{
    private $chatClient;

    private $startTime;

    public function __construct(Client $chatClient)
    {
        $this->chatClient = $chatClient;

        $this->startTime = new \DateTimeImmutable('@' . PROCESS_START_TIME);
    }

    /**
     * Handle a command message
     *
     * @param CommandMessage $command
     * @return Promise
     */
    public function handleCommand(CommandMessage $command): Promise
    {
        $lastAccident = dateinterval_to_string((new \DateTime)->diff($this->startTime));
        $since = $this->startTime->format('Y-m-d H:i:s');

        $lastAccidentMessage = " [" . $lastAccident . "] without an accident ";
        $sinceMessage = " since [" . $since . "] ";

        $lineLength = max(strlen($lastAccidentMessage), strlen($sinceMessage));

        $message  = "╔" . str_repeat("═", $lineLength) . "╗\n";
        $message .= "║" . str_pad($lastAccidentMessage, $lineLength, " ", STR_PAD_BOTH) . "║\n";
        $message .= "║" . str_pad($sinceMessage, $lineLength, " ", STR_PAD_BOTH) . "║\n";
        $message .= "╚" . str_repeat("═", $lineLength) . "╝\n";

        return $this->chatClient->postMessage($command, $message, PostFlags::FIXED_FONT);
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
