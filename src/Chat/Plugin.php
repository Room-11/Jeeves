<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat;

use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Message\Message;

interface Plugin
{
    public function getName(): string;

    public function getHelpText(array $args): string;
    
    public function getCommandEndpoints(): array;

    public function getEventHandlers(): array;

    /**
     * @return callable|null
     */
    public function getMessageHandler();


    /**
     * Handle a general message
     *
     * @param Message $message
     * @return \Generator
    public function handleMessage(Message $message): \Generator;

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
    public function handleCommand(Command $command): \Generator;

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
    public function getHandledCommands(): array;

    /**
     * Returns true if the plugin handles all messages, false if it only handles specific commands
     *
     * @return bool
    public function handlesAllMessages(): bool;
     */
}
