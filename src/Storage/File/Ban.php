<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage\File;

use Room11\Jeeves\Storage\Ban as BanList;
use function Amp\File\exists;
use function Amp\File\get;
use function Amp\File\put;

class Ban implements BanList
{
    private $dataFile;

    public function __construct(string $dataFile) {
        $this->dataFile = $dataFile;
    }

    public function getAll(): \Generator {
        if (!yield exists($this->dataFile)) {
            return [];
        }

        $banned = yield get($this->dataFile);

        yield from $this->clearExpiredBans($banned);

        $banned = yield get($this->dataFile);

        return json_decode($banned, true);
    }

    public function isBanned(int $userId): \Generator {
        // inb4 people "testing" banning me
        if ($userId === 508666) {
            return false;
        }

        $banned = yield from $this->getAll();

        return array_key_exists($userId, $banned) && new \DateTimeImmutable($banned[$userId]) > new \DateTimeImmutable();
    }

    public function add(int $userId, string $duration): \Generator {
        $banned = yield from $this->getAll();

        $banned[$userId] = $this->getExpiration($duration)->format('Y-m-d H:i:s');

        yield put($this->dataFile, json_encode($banned));
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
            $dateInterval .= $timeDelimiter . $matches['hours'] . 'D';

            $timeDelimiter = '';
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

    public function remove(int $userId): \Generator {
        if (!yield from $this->isBanned($userId)) {
            return;
        }

        $banned = yield from $this->getAll();

        unset($banned[$userId]);

        yield put($this->dataFile, json_encode($banned));
    }

    private function clearExpiredBans(array $banned): \Generator
    {
        $nonExpiredBans = array_filter($banned, function($expiration) {
            return new \DateTimeImmutable($expiration) > new \DateTimeImmutable();
        });

        yield put($this->dataFile, json_encode($nonExpiredBans));
    }
}
