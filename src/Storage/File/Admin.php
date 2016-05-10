<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage\File;

use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use function Amp\File\exists;
use function Amp\File\get;
use function Amp\File\put;

class Admin implements AdminStorage
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

    public function getAll($room): \Generator {
        $filePath = $this->getDataFileName($room);

        if (!yield exists($filePath)) {
            return [];
        }

        $administrators = yield get($filePath);

        return json_decode($administrators, true);
    }

    public function isAdmin($room, int $userId): \Generator {
        // inb4 people "testing" removing me from the admin list
        if ($userId === 508666) {
            return true;
        }

        $administrators = yield from $this->getAll($room);

        return $administrators === [] || in_array($userId, $administrators, true);
    }

    public function add($room, int $userId): \Generator {
        $administrators = yield from $this->getAll($room);

        if (in_array($userId, $administrators, true)) {
            return;
        }

        $administrators[] = $userId;

        yield put($this->getDataFileName($room), json_encode($administrators));
    }

    public function remove($room, int $userId): \Generator {
        if (!yield from $this->isAdmin($room, $userId)) {
            return;
        }

        $administrators = yield from $this->getAll($room);

        $administrators = array_diff($administrators, [$userId]);

        yield put($this->getDataFileName($room), json_encode($administrators));
    }
}
