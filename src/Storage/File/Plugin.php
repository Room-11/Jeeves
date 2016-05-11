<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage\File;

use Amp\Promise;
use Room11\Jeeves\Storage\Plugin as PluginStorage;
use function Amp\File\exists;
use function Amp\File\get;
use function Amp\File\put;
use function Amp\resolve;

class Plugin implements PluginStorage
{
    private $dataFileTemplate;

    public function __construct(string $dataFile)
    {
        $this->dataFileTemplate = $dataFile;
    }

    private function getDataFileName(string $room): string
    {
        return sprintf($this->dataFileTemplate, $room);
    }

    private function read($room): \Generator
    {
        $filePath = $this->getDataFileName($room);

        return (yield exists($filePath))
            ? json_try_decode(yield get($filePath), true)
            : [];
    }

    private function write($room, array $data): \Generator
    {
        yield put($this->getDataFileName($room), json_encode($data));
    }

    public function isPluginEnabled(string $room, string $plugin): Promise
    {
        return resolve(function() use($room, $plugin) {
            $data = yield from $this->read($room);

            return $data[strtolower($plugin)]['enabled'] ?? true;
        });
    }

    public function setPluginEnabled(string $room, string $plugin, bool $enabled): Promise
    {
        return resolve(function() use($room, $plugin, $enabled) {
            $data = yield from $this->read($room);

            $data[strtolower($plugin)]['enabled'] = $enabled;

            yield from $this->write($room, $data);
        });
    }

    public function getAllMappedCommands(string $room, string $plugin): Promise
    {
        return resolve(function() use($room, $plugin) {
            $data = yield from $this->read($room);

            return $data[strtolower($plugin)]['commands'] ?? null;
        });
    }

    public function addCommandMapping(string $room, string $plugin, string $command, string $endpoint): Promise
    {
        return resolve(function() use($room, $plugin, $command, $endpoint) {
            $data = yield from $this->read($room);

            $data[strtolower($plugin)]['commands'][$command] = $endpoint;

            yield from $this->write($room, $data);
        });
    }

    public function removeCommandMapping(string $room, string $plugin, string $command): Promise
    {
        return resolve(function() use($room, $plugin, $command) {
            $data = yield from $this->read($room);

            unset($data[strtolower($plugin)]['commands'][$command]);

            yield from $this->write($room, $data);
        });
    }
}
