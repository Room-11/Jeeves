<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage\File;

use Amp\Promise;
use Room11\Jeeves\Storage\CommandAlias as CommandAliasStorage;
use Room11\StackChat\Room\Room as ChatRoom;
use function Amp\resolve;

class CommandAlias implements CommandAliasStorage
{
    private $accessor;
    private $dataFileTemplate;

    public function __construct(JsonFileAccessor $accessor, string $dataFile) {
        $this->dataFileTemplate = $dataFile;
        $this->accessor = $accessor;
    }

    public function getAll(ChatRoom $room): Promise
    {
        return $this->accessor->read($this->dataFileTemplate, $room);
    }

    public function get(ChatRoom $room, string $command): Promise
    {
        return resolve(function() use($room, $command) {
            $data = yield $this->accessor->read($this->dataFileTemplate, $room);
            return $data[$command] ?? null;
        });
    }

    public function exists(ChatRoom $room, string $command): Promise
    {
        return resolve(function() use($room, $command) {
            return array_key_exists($command, yield $this->accessor->read($this->dataFileTemplate, $room));
        });
    }

    public function set(ChatRoom $room, string $command, string $mapping): Promise
    {
        return $this->accessor->writeCallback(function($data) use($command, $mapping) {
            $data[$command] = $mapping;
            return $data;
        }, $this->dataFileTemplate, $room);
    }

    public function remove(ChatRoom $room, string $command): Promise
    {
        return $this->accessor->writeCallback(function($data) use($command) {
            unset($data[$command]);
            return $data;
        }, $this->dataFileTemplate, $room);
    }
}
