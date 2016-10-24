<?php declare(strict_types = 1);
/**
 * Created by PhpStorm.
 * User: chris.wright
 * Date: 19/09/2016
 * Time: 22:06
 */

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Event\Traits\UserSource;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class Invitation extends BaseEvent implements UserSourcedEvent, GlobalEvent
{
    use UserSource;

    const TYPE_ID = EventType::INVITATION;

    private $roomId;
    private $roomName;

    public function __construct(array $data, ChatRoom $room)
    {
        parent::__construct($data, $room->getIdentifier()->getHost());

        $this->userId = (int)$data['user_id'];
        $this->userName = (string)$data['user_name'];

        $this->roomId = (int)$data['room_id'];
        $this->roomName = (string)$data['room_name'];
    }

    public function getTypeId(): int
    {
        return static::TYPE_ID;
    }

    public function getRoomId(): int
    {
        return $this->roomId;
    }

    public function getRoomName(): string
    {
        return $this->roomName;
    }
}
