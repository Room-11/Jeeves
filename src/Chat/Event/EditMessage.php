<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\WebSocket\Handler as WebSocketHandler;

class EditMessage extends MessageEvent
{
    const TYPE_ID = EventType::MESSAGE_EDITED;

    private $numberOfEdits;

    public function __construct(array $data, WebSocketHandler $handler)
    {
        parent::__construct($data, $handler);

        $this->numberOfEdits = (int)$data['message_edits'];
    }

    public function getNumberOfEdits(): int
    {
        return $this->numberOfEdits;
    }
}
