<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\PostedMessageTracker;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\System\BuiltInCommandInfo;
use function Amp\resolve;

class Remove implements BuiltInCommand
{
    const BIN_ROOM_ID = 48058;

    private $chatClient;
    private $admin;
    private $tracker;

    public function __construct(ChatClient $chatClient, AdminStorage $admin, PostedMessageTracker $tracker)
    {
        $this->chatClient = $chatClient;
        $this->admin = $admin;
        $this->tracker = $tracker;
    }

    private function remove(CommandMessage $command)
    {
        if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
            return $this->chatClient->postReply($command, "Sorry, you're not cool enough to do that :(");
        }

        if (!yield $this->chatClient->isBotUserRoomOwner($command->getRoom())) {
            return $this->chatClient->postReply($command, "Sorry, I'm not a room owner so I can't do that :(");
        }

        if ($this->tracker->getCount($command->getRoom()) === 0) {
            return $this->chatClient->postReply($command, "I don't have any messages stored for this room, sorry");
        }

        return $this->removeMessages($command, (int)($command->getParameter(0) ?? 1));
    }

    private function removeMessages(CommandMessage $command, int $count)
    {
        $room = $command->getRoom();
        $messages = [$command->getId()];

        for ($i = 0; $i < $count && null !== $message = $this->tracker->popMessage($room); $i++) {
            $messages[] = $message->getId();

            $commandMessage = $message->getOriginatingCommand();
            if ($commandMessage !== null) {
                $messages[] = $commandMessage->getId();
            }
        }

        return $this->chatClient->moveMessages($room, self::BIN_ROOM_ID, ...$messages);
    }

    public function handleCommand(CommandMessage $command): Promise
    {
        return resolve($this->remove($command));
    }

    public function getCommandInfo(): array
    {
        return [
            new BuiltInCommandInfo('remove', "Remove the last x messages posted by the bot", BuiltInCommandInfo::REQUIRE_ADMIN_USER),
        ];
    }
}
