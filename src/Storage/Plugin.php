<?php

namespace Room11\Jeeves\Storage;

use Amp\Promise;

interface Plugin
{
    public function isPluginEnabled(string $room, string $plugin): Promise;

    public function setPluginEnabled(string $room, string $plugin, bool $enabled): Promise;

    public function getAllMappedCommands(string $room, string $plugin): Promise;

    public function addCommandMapping(string $room, string $plugin, string $command, string $endpoint): Promise;

    public function removeCommandMapping(string $room, string $plugin, string $command): Promise;
}
