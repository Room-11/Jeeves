<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

interface IdentifiableMessage extends ChatRoomContainer
{
    function getId(): int;
}
