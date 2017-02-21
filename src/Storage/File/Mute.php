<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage\File;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Storage\Mute as MuteStorage;
use function Amp\resolve;

class Mute implements MuteStorage
{
    private $accessor;
    private $dataFileTemplate;

    public function __construct(JsonFileAccessor $accessor, string $dataFile)
    {
        $this->dataFileTemplate = $dataFile;
        $this->accessor = $accessor;
    }

    public function getAll(): Promise
    {
        return $this->accessor->writeCallback(function ($data) {
            $now = new \DateTimeImmutable();

            return array_filter($data, function ($expiration) use ($now) {
                return new \DateTimeImmutable($expiration) > $now;
            });
        }, $this->dataFileTemplate);
    }

    public function isMuted(ChatRoomIdentifier $roomIdentifier): Promise
    {
        return resolve(function () use ($roomIdentifier) {
            $muted = yield $this->accessor->read($this->dataFileTemplate);
            return array_key_exists($roomIdentifier->getId(), $muted);
        });
    }

    public function add(ChatRoomIdentifier $roomIdentifier): Promise
    {
        return $this->accessor->writeCallback(function ($data) use ($roomIdentifier) {
            $data[$roomIdentifier->getId()] = true;
            return $data;
        }, $this->dataFileTemplate);
    }

    public function remove(ChatRoomIdentifier $roomIdentifier): Promise
    {
        return $this->accessor->writeCallback(function ($data) use ($roomIdentifier) {
            unset($data[$roomIdentifier->getId()]);
            return $data;
        }, $this->dataFileTemplate);
    }
}
