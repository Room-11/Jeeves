<?php

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Command\Message;

/**
 * Provides default interface method implementations for plugins which only handle commands
 */
trait CommandOnlyPlugin
{
    public function handleMessage(Message $message): \Generator { yield; }

    public function handlesAllMessages(): bool { return false; }
}
