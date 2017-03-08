<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\System\BuiltInCommandInfo;
use function Amp\resolve;

class Version implements BuiltInCommand
{
    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    private function getVersion(CommandMessage $command)
    {
        if (!yield $command->getRoom()->isApproved()) {
            return null;
        }

        $version = \Room11\Jeeves\get_current_version();

        return $this->chatClient->postMessage($command, sprintf(
            "[%s](%s)",
            $version->getVersionString(),
            $version->getGithubUrl()
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
        return resolve($this->getVersion($command));
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getCommandInfo(): array
    {
        return [
            new BuiltInCommandInfo('version', "Display the current running version of the bot."),
        ];
    }
}
