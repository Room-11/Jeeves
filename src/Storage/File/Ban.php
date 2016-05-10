<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage\File;

use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use function Amp\File\exists;
use function Amp\File\get;
use function Amp\File\put;

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
    private function getDataFileName($room): string {
        if ($room instanceof ChatRoom) {
            $room = $room->getIdentifier();
        }

        $roomId = $room instanceof ChatRoomIdentifier ? $room->getIdentString() : (string)$room;
        return sprintf($this->dataFileTemplate, $roomId);
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @return \Generator
     */
    public function getAll($room): \Generator {
        $filePath = $this->getDataFileName($room);

        if (!yield exists($filePath)) {
            return [];
        }

        $banned = yield get($filePath);

        yield from $this->clearExpiredBans($room, json_decode($banned, true));

        $banned = yield get($filePath);

        return json_decode($banned, true);
    }

    public function isBanned($room, int $userId): \Generator {
        // inb4 people "testing" banning me
        if ($userId === 508666) {
            return false;
        }

        $banned = yield from $this->getAll($room);

        return array_key_exists($userId, $banned) && new \DateTimeImmutable($banned[$userId]) > new \DateTimeImmutable();
    }

    public function add($room, int $userId, string $duration): \Generator {
        $banned = yield from $this->getAll($room);

        $banned[$userId] = $this->getExpiration($duration)->format('Y-m-d H:i:s');

        yield put($this->getDataFileName($room), json_encode($banned));
    }

    // duration string should be in the format of [nd(ays)][nh(ours)][nm(inutes)][ns(econds)]
    private function getExpiration(string $duration): \DateTimeImmutable {
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

    public function remove($room, int $userId): \Generator {
        if (!yield from $this->isBanned($room, $userId)) {
            return;
        }

        $banned = yield from $this->getAll($room);

        unset($banned[$userId]);

        yield put($this->getDataFileName($room), json_encode($banned));
    }

    private function clearExpiredBans($room, array $banned): \Generator
    {
        $nonExpiredBans = array_filter($banned, function($expiration) {
            return new \DateTimeImmutable($expiration) > new \DateTimeImmutable();
        });

        yield put($this->getDataFileName($room), json_encode($nonExpiredBans));
    }
}
