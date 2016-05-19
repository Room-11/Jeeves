<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage\File;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use function Amp\File\exists;
use function Amp\File\get;
use function Amp\File\put;
use function Amp\resolve;

class Admin implements AdminStorage
{
    private $chatClient;
    private $dataFileTemplate;

    public function __construct(ChatClient $chatClient, string $dataFile)
    {
        $this->dataFileTemplate = $dataFile;
        $this->chatClient = $chatClient;
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

    private function getExtraAdmins(ChatRoom $room): \Generator
    {
        $filePath = $this->getDataFileName($room);

        if (!yield exists($filePath)) {
            return [];
        }

        $administrators = yield get($filePath);

        return json_decode($administrators, true);
    }

    public function getAll(ChatRoom $room): Promise
    {
        return resolve(function() use($room) {
            $owners = array_keys(yield $this->chatClient->getRoomOwners($room));
            $admins = yield from $this->getExtraAdmins($room);

            $reconciled = array_diff($admins, $owners);

            if (count($reconciled) !== count($admins)) {
                yield put($this->getDataFileName($room), json_encode($reconciled));
            }

            return [
                'owners' => $owners,
                'admins' => $reconciled,
            ];
        });
    }

    public function isAdmin(ChatRoom $room, int $userId): Promise
    {
        return resolve(function() use($room, $userId) {
            // inb4 people "testing" removing me from the admin list
            if ($userId === 508666) {
                return true;
            }

            $administrators = yield $this->getAll($room);

            return ($administrators['owners'] === [] && $administrators['admins'] === [])
                || in_array($userId, $administrators['owners'], true)
                || in_array($userId, $administrators['admins'], true);
        });
    }

    public function add(ChatRoom $room, int $userId): Promise
    {
        return resolve(function() use($room, $userId) {
            $administrators = yield from $this->getExtraAdmins($room);

            if (in_array($userId, $administrators, true)) {
                return;
            }

            $administrators[] = $userId;

            yield put($this->getDataFileName($room), json_encode($administrators));
        });
    }

    public function remove(ChatRoom $room, int $userId): Promise
    {
        return resolve(function() use($room, $userId) {
            if (!yield $this->isAdmin($room, $userId)) {
                return;
            }

            $administrators = array_diff(yield from $this->getExtraAdmins($room), [$userId]);

            yield put($this->getDataFileName($room), json_encode($administrators));
        });
    }
}
