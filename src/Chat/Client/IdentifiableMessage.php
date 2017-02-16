<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;

interface IdentifiableMessage
{
    function getRoom(): ChatRoom;
    function getId(): int;
}
