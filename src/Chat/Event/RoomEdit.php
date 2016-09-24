<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Event\Traits\RoomSource;
use Room11\Jeeves\Chat\Event\Traits\UserSource;
use Room11\Jeeves\Chat\WebSocket\Handler as WebSocketHandler;

class RoomEdit extends BaseEvent implements RoomSourcedEvent, UserSourcedEvent
{
    use RoomSource, UserSource;

    const TYPE_ID = EventType::ROOM_INFO_UPDATED;

    private $content;

    public function __construct(array $data, WebSocketHandler $handler)
    {
        parent::__construct($data, $handler);

        $this->room      = $handler->getRoom();

        $this->userId    = $data['user_id'];
        $this->userName  = $data['user_name'];

        $this->content   = $data['content'];
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
