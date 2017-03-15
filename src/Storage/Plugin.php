<?php

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

interface Plugin
{
    function isPluginEnabled(ChatRoom $room, string $plugin): Promise;

    function setPluginEnabled(ChatRoom $room, string $plugin, bool $enabled): Promise;

    function getAllMappedCommands(ChatRoom $room, string $plugin): Promise;

    function addCommandMapping(ChatRoom $room, string $plugin, string $command, string $endpoint): Promise;

    function removeCommandMapping(ChatRoom $room, string $plugin, string $command): Promise;
}
