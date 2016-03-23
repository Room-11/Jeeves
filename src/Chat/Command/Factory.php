<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Command;

use Room11\Jeeves\Chat\Command\Message as CommandMessage;
use Room11\Jeeves\Chat\Message\EditMessage;
use Room11\Jeeves\Chat\Message\MentionMessage;
use Room11\Jeeves\Chat\Message\Message;
use Room11\Jeeves\Chat\Message\NewMessage;

class Factory
{
    public function build(Message $message): CommandMessage
    {
        if ($this->isCommand($message)) {
            return new Command($message);
        }

        if ($this->isConversation($message)) {
            return new Conversation($message);
        }

        return new Void($message);
    }

    // Commands are messages that begin with !!
    private function isCommand(Message $message): bool
    {
        $postMessages = [
            NewMessage::class,
            EditMessage::class,
            MentionMessage::class,
        ];

        return in_array(get_class($message), $postMessages, true) && strpos($message->getContent(), '!!') === 0;
    }

    // Conversations are messages in which the bot is pinged / mentioned
    private function isConversation(Message $message): bool
    {
        return $message instanceof MentionMessage;
    }
}
