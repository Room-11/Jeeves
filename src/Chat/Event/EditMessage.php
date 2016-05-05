<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Message\Factory as MessageFactory;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class EditMessage extends MessageEvent
{
    const EVENT_TYPE_ID = 2;

    private $numberOfEdits;

    public function __construct(array $data, MessageFactory $messageFactory, ChatRoom $room)
    {
        parent::__construct($data, $messageFactory, $room);

        $this->numberOfEdits = (int)$data['message_edits'];
    }

    public function getNumberOfEdits(): int
    {
        return $this->numberOfEdits;
    }
}
