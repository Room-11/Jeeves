<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Command as CommandMessage;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\StackChat\Client\Client as ChatClient;

class Version implements BuiltInCommand
{
    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    /**
     * Handle a command message
     *
     * @param CommandMessage $command
     * @return Promise
     */
    public function handleCommand(CommandMessage $command): Promise
    {
        $version = \Room11\Jeeves\get_current_version();

        return $this->chatClient->postMessage($command, sprintf(
            "[%s](%s)",
            $version->getVersionString(),
            $version->getGithubUrl()
        ));
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
