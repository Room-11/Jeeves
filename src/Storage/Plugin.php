<?php

namespace Room11\Jeeves\Storage;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

interface Plugin
{
    public function isPluginEnabled(ChatRoom $room, string $plugin): Promise;

    public function setPluginEnabled(ChatRoom $room, string $plugin, bool $enabled): Promise;

    public function getAllMappedCommands(ChatRoom $room, string $plugin): Promise;

    public function addCommandMapping(ChatRoom $room, string $plugin, string $command, string $endpoint): Promise;

    public function removeCommandMapping(ChatRoom $room, string $plugin, string $command): Promise;
}
