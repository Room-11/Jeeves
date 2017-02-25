<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Chat\Room\PresenceManager;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\System\BuiltInCommandInfo;
use function Amp\resolve;

/**
 * Class Mute
 * TODO: Mute Duration
 * @package Room11\Jeeves\BuiltIn\Commands
 */
class Mute implements BuiltInCommand
{
    private $chatClient;
    private $adminStorage;
    private $presenceManager;

    public function __construct(ChatClient $chatClient, AdminStorage $adminStorage, PresenceManager $presenceManager)
    {
        $this->chatClient   = $chatClient;
        $this->adminStorage = $adminStorage;
        $this->presenceManager = $presenceManager;
    }

    private function execute(CommandMessage $command)
    {

        if (!yield $command->getRoom()->isApproved()) {
            return null;
        }

        if (!yield $this->adminStorage->isAdmin($command->getRoom(), $command->getUserId())) {
            return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
        }

        if ($command->getCommandName() === "mute") {
            yield from $this->mute($command);
        } elseif ($command->getCommandName() === "unmute") {
            yield from $this->unMute($command);
        }

        return null;
    }

    /**
     * Handle muting a room from a command.
     * @param CommandMessage $command
     * @return \Generator
     */
    private function mute(CommandMessage $command): \Generator
    {
        yield $this->chatClient->postMessage($command, "I'll be quiet.");
        yield $this->presenceManager->muteForever($command->getRoom()->getIdentifier());
    }

    /**
     * Handle unmuting a room from a command.
     * @param CommandMessage $command
     * @return \Generator
     */
    private function unMute(CommandMessage $command): \Generator
    {
        yield $this->presenceManager->unMute($command->getRoom()->getIdentifier());

        yield $this->chatClient->postMessage($command, 'Speaking freely.');
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
     * Get a list of specific commands handled by this plugin
     *
     * @return BuiltInCommandInfo[]
     */
    public function getCommandInfo(): array
    {
        return [
            new BuiltInCommandInfo('mute', "Silence Jeeves from speaking.", true),
            new BuiltInCommandInfo('unmute', "Allow Jeeves to speak again.", true),
        ];
    }
}
