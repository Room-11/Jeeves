<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage\File;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\Ban as BanStorage;
use function Amp\resolve;

class Ban implements BanStorage
{
    private $accessor;
    private $dataFileTemplate;

    public function __construct(JsonFileAccessor $accessor, string $dataFile) {
        $this->dataFileTemplate = $dataFile;
        $this->accessor = $accessor;
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
    public function getAll(ChatRoom $room): Promise
    {
        return $this->accessor->writeCallback(function($data) {
            $now = new \DateTimeImmutable();

            return array_filter($data, function($expiration) use($now) {
                return new \DateTimeImmutable($expiration) > $now;
            });
        }, $this->dataFileTemplate, $room);
    }

    public function isBanned(ChatRoom $room, int $userId): Promise
    {
        return resolve(function() use($room, $userId) {
            $banned = yield $this->accessor->read($this->dataFileTemplate, $room);

            return array_key_exists($userId, $banned)
                && new \DateTimeImmutable($banned[$userId]) > new \DateTimeImmutable();
        });
    }

    public function add(ChatRoom $room, int $userId, string $duration): Promise
    {
        return $this->accessor->writeCallback(function($data) use($userId, $duration) {
            $data[$userId] = $this->getExpiration($duration)->format('Y-m-d H:i:s');
            return $data;
        }, $this->dataFileTemplate, $room);
    }

    public function remove(ChatRoom $room, int $userId): Promise
    {
        return $this->accessor->writeCallback(function($data) use($userId) {
            unset($data[$userId]);
            return $data;
        }, $this->dataFileTemplate, $room);
    }
}
