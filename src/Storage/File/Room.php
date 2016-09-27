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

    private function reconcileVotes(array $votes, int $addUserId = null): array
    {
        $now = time();
        $deleteOlderThan = $now - 86400; // -1 day
        $result = [];

        foreach ($votes as $userId => $timestamp) {
            if ($timestamp >= $deleteOlderThan) {
                $result[$userId] = $timestamp;
            }
        }

        if ($addUserId !== null) {
            $result[$addUserId] = $now;
        }

        return $result;
    }

    public function addRoom(ChatRoomIdentifier $room, int $inviteTimestamp): Promise
    {
        return $this->accessor->writeCallback(function($data) use($room, $inviteTimestamp) {
            $data[$room->getIdentString()] = [
                'approve_votes'    => [],
                'leave_votes'      => [],
                'is_approved'      => false,
                'invite_timestamp' => $inviteTimestamp,
            ];

            return $data;
        }, $this->dataFileTemplate);
    }

    public function removeRoom(ChatRoomIdentifier $room): Promise
    {
        return $this->accessor->writeCallback(function($data) use($room) {
            unset($data[$room->getIdentString()]);
            return $data;
        }, $this->dataFileTemplate);
    }

    public function getAllRooms(): Promise
    {
        return resolve(function() {
            return array_keys(yield $this->accessor->read($this->dataFileTemplate));
        });
    }

    public function getInviteTimestamp(ChatRoomIdentifier $room): Promise
    {
        return resolve(function() use($room) {
            $data = yield $this->accessor->read($this->dataFileTemplate);
            return $data[$room->getIdentString()]['invite_timestamp'] ?? 0;
        });
    }

    public function addApproveVote(ChatRoomIdentifier $room, int $userId): Promise
    {
        return $this->accessor->writeCallback(function($data) use($room, $userId) {
            $ident = $room->getIdentString();

            $data[$ident]['approve_votes'] = $this->reconcileVotes($data[$ident]['approve_votes'], $userId);

            return $data;
        }, $this->dataFileTemplate);
    }

    public function getApproveVotes(ChatRoomIdentifier $room): Promise
    {
        return resolve(function() use($room) {
            $ident = $room->getIdentString();

            yield $this->accessor->writeCallback(function($data) use($ident) {
                $data[$ident]['approve_votes'] = $this->reconcileVotes($data[$ident]['approve_votes'] ?? []);
                return $data;
            }, $this->dataFileTemplate);

            $data = yield $this->accessor->read($this->dataFileTemplate);
            return $data[$ident]['approve_votes'] ?? [];
        });
    }

    public function addLeaveVote(ChatRoomIdentifier $room, int $userId): Promise
    {
        return $this->accessor->writeCallback(function($data) use($userId, $room) {
            $ident = $room->getIdentString();

            $data[$ident]['leave_votes'] = $this->reconcileVotes($data[$ident]['leave_votes'], $userId);

            return $data;
        }, $this->dataFileTemplate);
    }

    public function getLeaveVotes(ChatRoomIdentifier $room): Promise
    {
        return resolve(function() use($room) {
            $ident = $room->getIdentString();

            yield $this->accessor->writeCallback(function($data) use($ident) {
                $data[$ident]['leave_votes'] = $this->reconcileVotes($data[$ident]['leave_votes'] ?? []);
                return $data;
            }, $this->dataFileTemplate);

            $data = yield $this->accessor->read($this->dataFileTemplate);
            return $data[$ident]['leave_votes'] ?? [];
        });
    }

    public function setApproved(ChatRoomIdentifier $room): Promise
    {
        return $this->accessor->writeCallback(function($data) use($room) {
            $data[$room->getIdentString()]['is_approved'] = true;
            return $data;
        }, $this->dataFileTemplate);
    }

    public function isApproved(ChatRoomIdentifier $room): Promise
    {
        return resolve(function() use($room) {
            $data = yield $this->accessor->read($this->dataFileTemplate);
            return $data[$room->getIdentString()]['is_approved'] ?? false;
        });
    }
}
