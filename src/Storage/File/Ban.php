<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage\File;

use Amp\Promise;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use function Amp\File\exists;
use function Amp\File\get;
use function Amp\File\put;
use function Amp\resolve;

class Ban implements BanStorage
{
    private $dataFileTemplate;

    public function __construct(string $dataFile) {
        $this->dataFileTemplate = $dataFile;
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @return string
     */
    private function getDataFileName($room): string
    {
        if ($room instanceof ChatRoom) {
            $room = $room->getIdentifier();
        }

        $roomId = $room instanceof ChatRoomIdentifier ? $room->getIdentString() : (string)$room;
        return sprintf($this->dataFileTemplate, $roomId);
    }

    private function promise(callable $generator): Promise
    {
        return resolve($generator());
    }

    // duration string should be in the format of [nd(ays)][nh(ours)][nm(inutes)][ns(econds)]
    private function getExpiration(string $duration): \DateTimeImmutable
    {
        $expiration = new \DateTimeImmutable();

        if (!preg_match('/^((?P<days>\d+)d)?((?P<hours>\d+)h)?((?P<minutes>\d+)m)?((?P<seconds>\d+)s)?$/', $duration, $matches)) {
            return $expiration;
        }

        $dateInterval  = 'P';
        $timeDelimiter = 'T';

        if (!empty($matches['days'])) {
            $dateInterval .= $matches['days'] . 'D';
        }

        if (!empty($matches['hours'])) {
            $dateInterval .= $timeDelimiter . $matches['hours'] . 'H';

            $timeDelimiter = '';
        }

        if (!empty($matches['minutes'])) {
            $dateInterval .= $timeDelimiter . $matches['minutes'] . 'M';

            $timeDelimiter = '';
        }

        if (!empty($matches['seconds'])) {
            $dateInterval .= $timeDelimiter . $matches['seconds'] . 'S';
        }

        return $expiration->add(new \DateInterval($dateInterval));
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @return Promise
     */
    public function getAll($room): Promise
    {
        return $this->promise(function() use($room) {
            $filePath = $this->getDataFileName($room);

            if (!yield exists($filePath)) {
                return [];
            }

            $banned = yield get($filePath);

            yield from $this->clearExpiredBans($room, json_decode($banned, true));

            $banned = yield get($filePath);

            return json_decode($banned, true);
        });
    }

    public function isBanned($room, int $userId): Promise
    {
        return $this->promise(function() use($room, $userId) {
            // inb4 people "testing" banning me
            if ($userId === 508666) {
                return false;
            }

            $banned = yield $this->getAll($room);

            return array_key_exists($userId, $banned) && new \DateTimeImmutable($banned[$userId]) > new \DateTimeImmutable();
        });
    }

    public function add($room, int $userId, string $duration): Promise
    {
        return $this->promise(function() use($room, $userId, $duration) {
            $banned = yield $this->getAll($room);

            $banned[$userId] = $this->getExpiration($duration)->format('Y-m-d H:i:s');

            yield put($this->getDataFileName($room), json_encode($banned));
        });
    }

    public function remove($room, int $userId): Promise
    {
        return $this->promise(function() use($room, $userId) {
            if (!yield $this->isBanned($room, $userId)) {
                return;
            }

            $banned = yield $this->getAll($room);

            unset($banned[$userId]);

            yield put($this->getDataFileName($room), json_encode($banned));
        });
    }

    private function clearExpiredBans($room, array $banned): Promise
    {
        return $this->promise(function() use($room, $banned) {
            $nonExpiredBans = array_filter($banned, function($expiration) {
                return new \DateTimeImmutable($expiration) > new \DateTimeImmutable();
            });

            yield put($this->getDataFileName($room), json_encode($nonExpiredBans));
        });
    }
}
