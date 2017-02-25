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

    private function reconcileLeaveVotes(string $ident, int $addUserId = null): Promise
    {
        $now = time();
        $deleteOlderThan = $now - self::MAX_LEAVE_VOTE_AGE;

        return $this->accessor->writeCallback(function($data) use($ident, $addUserId, $now, $deleteOlderThan) {
            $result = [];

            foreach ($data[$ident]['leave_votes'] ?? [] as $userId => $timestamp) {
                if ($timestamp >= $deleteOlderThan) {
                    $result[$userId] = $timestamp;
                }
            }

            if ($addUserId !== null) {
                $result[$addUserId] = $now;
            }

            $data[$ident]['leave_votes'] = $result;
            return $data;
        }, $this->dataFileTemplate);
    }

    public function containsRoom(ChatRoomIdentifier $identifier): Promise
    {
        return resolve(function() use($identifier) {
            return array_key_exists($identifier->getIdentString(), yield $this->accessor->read($this->dataFileTemplate));
        });
    }

    public function addRoom(ChatRoomIdentifier $identifier, int $inviteTimestamp): Promise
    {
        return $this->accessor->writeCallback(function($data) use($identifier, $inviteTimestamp) {
            $data[$identifier->getIdentString()] = [
                'approve_votes'    => [],
                'leave_votes'      => [],
                'is_approved'      => false,
                'is_muted_until'   => false,
                'invite_timestamp' => $inviteTimestamp,
            ];

            return $data;
        }, $this->dataFileTemplate);
    }

    public function removeRoom(ChatRoomIdentifier $identifier): Promise
    {
        return $this->accessor->writeCallback(function($data) use($identifier) {
            unset($data[$identifier->getIdentString()]);
            return $data;
        }, $this->dataFileTemplate);
    }

    public function getAllRooms(): Promise
    {
        return resolve(function() {
            return array_keys(yield $this->accessor->read($this->dataFileTemplate));
        });
    }

    public function getInviteTimestamp(ChatRoomIdentifier $identifier): Promise
    {
        return resolve(function() use($identifier) {
            $data = yield $this->accessor->read($this->dataFileTemplate);
            return $data[$identifier->getIdentString()]['invite_timestamp'] ?? 0;
        });
    }

    public function addApproveVote(ChatRoomIdentifier $identifier, int $userId): Promise
    {
        return $this->accessor->writeCallback(function($data) use($identifier, $userId) {
            $data[$identifier->getIdentString()]['approve_votes'][(string)$userId] = time();
            return $data;
        }, $this->dataFileTemplate);
    }

    public function containsApproveVote(ChatRoomIdentifier $identifier, int $userId): Promise
    {
        return resolve(function() use($identifier, $userId) {
            $data = yield $this->accessor->read($this->dataFileTemplate);
            return isset($data[$identifier->getIdentString()]['approve_votes'][(string)$userId]);
        });
    }

    public function getApproveVotes(ChatRoomIdentifier $identifier): Promise
    {
        return resolve(function() use($identifier) {
            $data = yield $this->accessor->read($this->dataFileTemplate);
            return $data[$identifier->getIdentString()]['approve_votes'] ?? [];
        });
    }

    public function containsLeaveVote(ChatRoomIdentifier $identifier, int $userId): Promise
    {
        return resolve(function() use($identifier, $userId) {
            $ident = $identifier->getIdentString();

            yield $this->reconcileLeaveVotes($ident);
            $data = yield $this->accessor->read($this->dataFileTemplate);

            return isset($data[$ident]['leave_votes'][(string)$userId]);
        });
    }

    public function addLeaveVote(ChatRoomIdentifier $identifier, int $userId): Promise
    {
        return $this->reconcileLeaveVotes($identifier->getIdentString(), $userId);
    }

    public function getLeaveVotes(ChatRoomIdentifier $identifier): Promise
    {
        return resolve(function() use($identifier) {
            $ident = $identifier->getIdentString();

            yield $this->reconcileLeaveVotes($ident);
            $data = yield $this->accessor->read($this->dataFileTemplate);

            return $data[$ident]['leave_votes'] ?? [];
        });
    }

    public function setApproved(ChatRoomIdentifier $identifier, bool $approved): Promise
    {
        return $this->accessor->writeCallback(function($data) use($identifier, $approved) {
            $data[$identifier->getIdentString()]['is_approved'] = $approved;
            return $data;
        }, $this->dataFileTemplate);
    }

    public function isApproved(ChatRoomIdentifier $identifier): Promise
    {
        return resolve(function() use($identifier) {
            $data = yield $this->accessor->read($this->dataFileTemplate);
            return $data[$identifier->getIdentString()]['is_approved'] ?? false;
        });
    }

    /**
     * @inheritDoc
     */
    public function isMuted(ChatRoomIdentifier $identifier): Promise
    {
        return resolve(function () use ($identifier) {
            $data = yield $this->accessor->read($this->dataFileTemplate);
            $until = $data[$identifier->getIdentString()]['is_muted_until'] ?? false;

            if ($until === false || $until === true) {
                // Un-muted or indefinitely muted.
                return $until;
            }

            if ($until < time()) {
                // Was muted, but the duration has passed.
                yield $this->unMute($identifier);
                return false;
            }

            return true;
        });
    }

    /**
     * @inheritDoc
     */
    public function isMutedForever(ChatRoomIdentifier $identifier): Promise
    {
        return resolve(function () use ($identifier) {
            $data = yield $this->accessor->read($this->dataFileTemplate);
            $until = $data[$identifier->getIdentString()]['is_muted_until'] ?? false;
            return $until === true;
        });
    }

    /**
     * @inheritDoc
     */
    public function getMuteExpiration(ChatRoomIdentifier $identifier): Promise
    {
        return resolve(function () use ($identifier) {
            $data = yield $this->accessor->read($this->dataFileTemplate);
            $until = $data[$identifier->getIdentString()]['is_muted_until'] ?? false;
            if ($until === true || $until === false) {
                return null;
            }

            return $until;
        });
    }

    /**
     * @inheritDoc
     */
    public function mute(ChatRoomIdentifier $identifier, int $expires): Promise
    {
        return $this->accessor->writeCallback(function ($data) use ($identifier, $expires) {
            $data[$identifier->getIdentString()]['is_muted_until'] = $expires;
            return $data;
        }, $this->dataFileTemplate);
    }

    /**
     * @inheritDoc
     */
    public function muteForever(ChatRoomIdentifier $identifier): Promise
    {
        return $this->accessor->writeCallback(function ($data) use ($identifier) {
            $data[$identifier->getIdentString()]['is_muted_until'] = true;
            return $data;
        }, $this->dataFileTemplate);
    }

    /**
     * @inheritDoc
     */
    public function unMute(ChatRoomIdentifier $identifier): Promise
    {
        return $this->accessor->writeCallback(function ($data) use ($identifier) {
            $identifierString = $identifier->getIdentString();
            if (array_key_exists('is_muted_until', $data[$identifierString])) {
                unset($data[$identifierString]['is_muted_until']);
            }
            return $data;
        }, $this->dataFileTemplate);
    }
}
