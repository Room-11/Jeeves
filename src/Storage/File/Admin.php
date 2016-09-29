<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage\File;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use function Amp\resolve;

class Admin implements AdminStorage
{
    private $accessor;
    private $chatClient;
    private $dataFileTemplate;

    public function __construct(JsonFileAccessor $accessor, ChatClient $chatClient, string $dataFile)
    {
        $this->accessor = $accessor;
        $this->chatClient = $chatClient;
        $this->dataFileTemplate = $dataFile;
    }

    public function getAll(ChatRoom $room): Promise
    {
        return resolve(function() use($room) {
            $owners = array_keys(yield $this->chatClient->getRoomOwners($room));

            $admins = yield $this->accessor->writeCallback(function($data) use($owners) {
                return array_values(array_diff($data, $owners));
            }, $this->dataFileTemplate, $room);

            return [
                'owners' => $owners,
                'admins' => $admins,
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
        return $this->accessor->writeCallback(function($data) use($userId) {
            $data[] = $userId;
            return array_unique($data);
        }, $this->dataFileTemplate, $room);
    }

    public function remove(ChatRoom $room, int $userId): Promise
    {
        return $this->accessor->writeCallback(function($data) use($userId) {
            if (false !== $key = array_search($userId, $data)) {
                array_splice($data, $key, 1);
            }
            return $data;
        }, $this->dataFileTemplate, $room);
    }
}
