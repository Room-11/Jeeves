<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use function Amp\resolve;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\PendingMessage;
use Room11\Jeeves\Chat\Client\PostedMessageTracker;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\BuiltInCommandInfo;

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

    private function remove(CommandMessage $command): \Generator
    {
        if (!yield $command->getRoom()->isApproved()) {
            return null;
        }

        if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(
                    'Sorry, you\'re not cool enough to do that :(',
                    $command
                )
            );
        }

        $messages = $this->tracker->getAll($command->getRoom());

        if (count($messages) === 0) {
            return $this->chatClient->postReply(
                $command,
                new PendingMessage(
                    'I don\'t have any messages stored for this room, sorry',
                    $command
                )
            );
        }

        $count = (int)($command->getParameter(0) ?? 1);
        yield $this->removeMessages($command->getRoom(), $count, $command->getId());
    }

    private function removeMessages(ChatRoom $room, int $count, int $additionalMessageId)
    {
        $messages = [$additionalMessageId];

        for ($i = 0; $i < $count && null !== $message = $this->tracker->popMessage($room); $i++) {
            $messages[] = $message->getId();

            $commandMessage = $message->getMessage()->getOriginatingCommand();
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
            new BuiltInCommandInfo('remove', "Remove the last x messages posted by the bot", true),
        ];
    }
}
