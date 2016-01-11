<?php

namespace Room11\Jeeves\Chat\Command;

use Room11\Jeeves\Chat\Message\Message;

interface Command
{
    public function handle(Message $message);

    public function getHelp(Message $message);

    public function getDescription(Message $message);
}
