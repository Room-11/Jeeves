<?php declare(strict_types = 1);

namespace Room11\Jeeves\Storage\File;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Storage\Room as RoomStorage;
use function Amp\resolve;

class Room implements RoomStorage
{
    private $accessor;
    private $dataFileTemplate;

    public function __construct(JsonFileAccessor $accessor, string $dataFile)
    {
        $this->accessor = $accessor;
        $this->dataFileTemplate = $dataFile;
    }

    public function addWelcomeVote(ChatRoomIdentifier $room, int $userId): Promise
    {
        return $this->accessor->writeCallback(function($data) use($userId) {
            $data['welcome'][] = $userId;
            return $data;
        }, $this->dataFileTemplate, $room);
    }

    public function getWelcomeVotes(ChatRoomIdentifier $room): Promise
    {
        return resolve(function() use($room) {
            $data = yield $this->accessor->read($this->dataFileTemplate, $room);

            return $data['welcome'] ?? [];
        });
    }

    public function addLeaveVote(ChatRoomIdentifier $room, int $userId): Promise
    {
        return $this->accessor->writeCallback(function($data) use($userId) {
            $data['leave'][] = $userId;
            return $data;
        }, $this->dataFileTemplate, $room);
    }

    public function getLeaveVotes(ChatRoomIdentifier $room): Promise
    {
        return resolve(function() use($room) {
            $data = yield $this->accessor->read($this->dataFileTemplate, $room);

            return $data['leave'] ?? [];
        });
    }

    public function clear(ChatRoomIdentifier $room)
    {
        return $this->accessor->write([], $this->dataFileTemplate, $room);
    }
}
