<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\BuiltIn;

use Room11\Jeeves\Chat\BuiltInCommand;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use SebastianBergmann\Version as SebastianVersion;

class Version implements BuiltInCommand
{
    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    private function getVersion(CommandMessage $command): \Generator
    {
        $version = (new SebastianVersion(VERSION, dirname(dirname(dirname(__DIR__)))))->getVersion();

        $version = preg_replace_callback('@v([0-9.]+)(?:-\d+-g([0-9a-f]+))?@', function($match) {
            return sprintf(
                "[%s](%s)",
                $match[0],
                empty($match[2])
                    ? "https://github.com/Room-11/Jeeves/tree/v" . $match[1]
                    : "https://github.com/Room-11/Jeeves/commit/" . $match[2]
            );
        }, $version);

        yield from $this->chatClient->postMessage($command->getRoom(), $version);
    }

    /**
     * Handle a command message
     *
     * @param CommandMessage $command
     * @return \Generator
     */
    public function handleCommand(CommandMessage $command): \Generator
    {
        yield from $this->getVersion($command);
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getCommandNames(): array
    {
        return ['version'];
    }
}
