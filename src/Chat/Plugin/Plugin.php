<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Command\Message;
use Room11\Jeeves\Chat\Command\Command;

interface Plugin
{
    /**
     * Handle a general message
     *
     * @param Message $message
     * @return \Generator
     */
    public function handleMessage(Message $message): \Generator;

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator;

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getHandledCommands(): array;

    /**
     * Returns true if the plugin handles all messages, false if it only handles specific commands
     *
     * @return bool
     */
    public function handlesAllMessages(): bool;
}
