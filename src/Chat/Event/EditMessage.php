<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class EditMessage extends MessageEvent
{
    const TYPE_ID = EventType::MESSAGE_EDITED;

    private $numberOfEdits;

    public function __construct(array $data, ChatRoom $room)
    {
        parent::__construct($data, $room);

        $this->numberOfEdits = (int)$data['message_edits'];
    }

    public function getNumberOfEdits(): int
    {
        return $this->numberOfEdits;
    }
}
