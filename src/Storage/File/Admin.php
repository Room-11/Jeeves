<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage\File;

use Room11\Jeeves\Storage\Admin as AdminList;
use function Amp\File\exists;
use function Amp\File\get;
use function Amp\File\put;

class Admin implements AdminList
{
    private $dataFile;

    public function __construct(string $dataFile) {
        var_dump($dataFile);
        $this->dataFile = $dataFile;
    }

    public function getAll(): \Generator {
        if (!yield exists($this->dataFile)) {
            return [];
        }

        $administrators = yield get($this->dataFile);

        return json_decode($administrators, true);
    }

    public function isAdmin(int $userId): \Generator {
        // inb4 people "testing" removing me from the admin list
        if ($userId === 508666) {
            return true;
        }

        $administrators = yield from $this->getAll();

        return $administrators === [] || in_array($userId, $administrators, true);
    }

    public function add(int $userId): \Generator {
        $administrators = yield from $this->getAll();

        if (in_array($userId, $administrators, true)) {
            return;
        }

        $administrators[] = $userId;

        yield put($this->dataFile, json_encode($administrators));
    }

    public function remove(int $userId): \Generator {
        if (!yield from $this->isAdmin($userId)) {
            return;
        }

        $administrators = yield from $this->getAll();

        $administrators = array_diff($administrators, [$userId]);

        yield put($this->dataFile, json_encode($administrators));
    }
}
