<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use function Amp\resolve;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\PendingMessage;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\BuiltInCommandInfo;

class Remove implements BuiltInCommand
{
    private $chatClient;
    private $admin;

    public function __construct(ChatClient $chatClient, AdminStorage $admin)
    {
        $this->chatClient = $chatClient;
        $this->admin = $admin;
    }

    private function remove(CommandMessage $command): \Generator
    {
        if (!yield $command->getRoom()->isApproved()) {
            return;
        }

        if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
            return $this->chatClient->postReply(
                $command, 
                new PendingMessage(
                    'Sorry, you\'re not cool enough to do that :(', 
                    $command->getId()
                )
            );
        }

        $messages = $this->chatClient->getStoredMessages($command->getRoom());
        if ($messages === false || $messages->count() === 0) {
            return $this->chatClient->postReply(
                $command, 
                new PendingMessage(
                    'I don\'t have any messages stored for this room, sorry', 
                    $command->getId()
                )
            );        
        }

        $amount = $command->getParameter(0) ?? 1;
        if ($amount > $messages->count()) {
            $amount = $messages->count();
        }

        yield from $this->removeMessages($command->getRoom(), (int) $amount, $command->getId());
    }

    private function removeMessages(ChatRoom $room, int $amount, int $commandId)
    {
        $messages = [];
        $messages[] = $commandId;

        for ($i = 0; $i < $amount; $i++) {
            $message = $this->chatClient->getAndRemoveStoredMessage($room);
            $messages[] = $message['messageId'];

            if (!is_null($message['commandId'])) {
                $messages[] = $message['commandId'];
            }
        }

        yield $this->chatClient->moveMessages($messages, $room);
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
