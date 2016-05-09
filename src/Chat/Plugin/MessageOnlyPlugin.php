<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Message\Command;

trait MessageOnlyPlugin
{
    public function handleCommand(/** @noinspection PhpUnusedParameterInspection */ Command $command): \Generator { yield; }

    public function getHandledCommands(): array { return []; }

    public function handlesAllMessages(): bool { return true; }
}
