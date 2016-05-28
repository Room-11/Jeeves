<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Event\Traits\RoomSource;
use Room11\Jeeves\Chat\Event\Traits\UserSource;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class RoomEdit extends BaseEvent implements RoomSourcedEvent, UserSourcedEvent
{
    const TYPE_ID = 5;

    use RoomSource;
    use UserSource;

    private $content;

    public function __construct(array $data, ChatRoom $room)
    {
        parent::__construct($data);

        $this->room      = $room;

        $this->userId    = $data['user_id'];
        $this->userName  = $data['user_name'];

        $this->content   = $data['content'];
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
