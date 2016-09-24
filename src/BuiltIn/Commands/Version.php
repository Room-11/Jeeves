<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Storage\Room as RoomStorage;
use Room11\Jeeves\System\BuiltInCommand;
use SebastianBergmann\Version as SebastianVersion;
use const Room11\Jeeves\APP_BASE;
use function Amp\resolve;

class Version implements BuiltInCommand
{
    private $chatClient;
    private $roomStorage;

    public function __construct(ChatClient $chatClient, RoomStorage $roomStorage)
    {
        $this->chatClient = $chatClient;
        $this->roomStorage = $roomStorage;
    }

    private function getVersion(CommandMessage $command): \Generator
    {
        if (!yield $this->roomStorage->isApproved($command->getRoom()->getIdentifier())) {
            return;
        }

        $version = (new SebastianVersion(VERSION, APP_BASE))->getVersion();

        $messageText = preg_replace_callback('@v([0-9.]+)(?:-\d+-g([0-9a-f]+))?@', function($match) {
            return sprintf(
                "[%s](%s)",
                $match[0],
                empty($match[2])
                    ? "https://github.com/Room-11/Jeeves/tree/v" . $match[1]
                    : "https://github.com/Room-11/Jeeves/commit/" . $match[2]
            );
        }, $version);

        yield $this->chatClient->postMessage($command->getRoom(), $messageText);
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
    public function getCommandNames(): array
    {
        return ['version'];
    }
}
