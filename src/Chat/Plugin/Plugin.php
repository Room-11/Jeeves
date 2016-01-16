<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Command\Message;

interface Plugin
{
    public function handle(Message $message): \Generator;
}
