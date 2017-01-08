<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use function Amp\resolve;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\Admin as AdminStorage;

class remove implements BuiltInCommand
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
            return $this->chatClient->postReply($command, "Sorry, you're not cool enough to do that :(");
        }

        $messages = $this->chatClient->getStoredMessages($command->getRoom());
        if ($messages === false || $messages->count() === 0) {
            return $this->chatClient->postReply($command, 'I don\'t have any messages stored for this room, sorry');        
        }

        $amount = $command->getParameter(0) ?? 1;
        if ($amount > $messages->count()) {
            $amount = $messages->count();
        }

        yield from $this->removeMessages($command->getRoom(), $amount, $command->getId());
    }

    private function removeMessages(ChatRoom $room, int $amount, int $commandId)
    {
        $messages = [];
        for ($i = 0; $i < $amount; $i++) {
            $messages[] = $this->chatClient->getAndRemoveStoredMessage($room);
        }
        $messages[] = $commandId;
        
        yield $this->chatClient->moveMessages($messages, $room);
    }

    public function handleCommand(CommandMessage $command): Promise
    {
        return resolve($this->remove($command));
    }

    public function getCommandNames(): array
    {
        return ['remove'];
    }
}
