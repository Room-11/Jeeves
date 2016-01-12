<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Command;

use Room11\Jeeves\Chat\Message\Message;

interface Command
{
    public function handle(Message $message): \Generator;
}
