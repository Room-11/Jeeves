<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage\File;

use Amp\Promise;
use Room11\Jeeves\Storage\Plugin as PluginStorage;
use Room11\StackChat\Room\Room as ChatRoom;
use function Amp\resolve;

class Plugin implements PluginStorage
{
    private $accessor;
    private $dataFileTemplate;

    public function __construct(JsonFileAccessor $accessor, string $dataFile)
    {
        $this->accessor = $accessor;
        $this->dataFileTemplate = $dataFile;
    }

    public function isPluginEnabled(ChatRoom $room, string $plugin): Promise
    {
        return resolve(function() use($room, $plugin) {
            $data = yield $this->accessor->read($this->dataFileTemplate, $room);

            return $data[strtolower($plugin)]['enabled'] ?? true;
        });
    }

    public function setPluginEnabled(ChatRoom $room, string $plugin, bool $enabled): Promise
    {
        return $this->accessor->writeCallback(function($data) use($plugin, $enabled) {
            $data[strtolower($plugin)]['enabled'] = $enabled;
            return $data;
        }, $this->dataFileTemplate, $room);
    }

    public function getAllMappedCommands(ChatRoom $room, string $plugin): Promise
    {
        return resolve(function() use($room, $plugin) {
            $data = yield $this->accessor->read($this->dataFileTemplate, $room);

            return $data[strtolower($plugin)]['commands'] ?? null;
        });
    }

    public function addCommandMapping(ChatRoom $room, string $plugin, string $command, string $endpoint): Promise
    {
        return $this->accessor->writeCallback(function($data) use($plugin, $command, $endpoint) {
            $data[strtolower($plugin)]['commands'][$command] = $endpoint;
            return $data;
        }, $this->dataFileTemplate, $room);
    }

    public function removeCommandMapping(ChatRoom $room, string $plugin, string $command): Promise
    {
        return $this->accessor->writeCallback(function($data) use($plugin, $command) {
            unset($data[strtolower($plugin)]['commands'][$command]);
            return $data;
        }, $this->dataFileTemplate, $room);
    }
}
