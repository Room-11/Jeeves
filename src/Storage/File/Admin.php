<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage\File;

use Amp\Promise;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Room\AclDataAccessor;
use Room11\StackChat\Room\Room as ChatRoom;
use function Amp\resolve;

class Admin implements AdminStorage
{
    private $accessor;
    private $chatClient;
    private $aclDataAccessor;
    private $dataFileTemplate;

    public function __construct(
        JsonFileAccessor $accessor,
        ChatClient $chatClient,
        AclDataAccessor $aclDataAccessor,
        string $dataFile
    ) {
        $this->accessor = $accessor;
        $this->chatClient = $chatClient;
        $this->aclDataAccessor = $aclDataAccessor;
        $this->dataFileTemplate = $dataFile;
    }

    public function getAll(ChatRoom $room): Promise
    {
        return resolve(function() use($room) {
            $owners = array_keys(yield $this->aclDataAccessor->getRoomOwners($room));

            $admins = yield $this->accessor->writeCallback(function($data) use($owners) {
                return array_values(array_diff($data, $owners));
            }, $this->dataFileTemplate, $room);

            $siteModerators = array_keys(yield $this->aclDataAccessor->getMainSiteModerators($room));

            return [
                'owners' => $owners,
                'admins' => $admins,
                'site-moderators' => $siteModerators,
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
                || \in_array($userId, $administrators['owners'], true)
                || \in_array($userId, $administrators['admins'], true)
                || \in_array($userId, $administrators['site-moderators'], true);
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
