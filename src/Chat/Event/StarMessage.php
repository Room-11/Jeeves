<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Event\Traits\RoomSource;
use Room11\Jeeves\Chat\WebSocket\Handler as WebSocketHandler;

class StarMessage extends BaseEvent implements RoomSourcedEvent
{
    use RoomSource;

    const TYPE_ID = EventType::MESSAGE_STARRED;

    private $messageId;

    private $content;

    private $numberOfStars;

    private $pinned;

    public function __construct(array $data, WebSocketHandler $handler)
    {
        parent::__construct($data, $handler);

        $this->room          = $handler->getRoom();

        $this->messageId     = $data['message_id'];
        $this->content       = $data['content'];
        $this->numberOfStars = $data['message_stars'] ?? 0;
        $this->pinned        = isset($data['message_owner_stars']);
    }

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getNumberOfStars(): int
    {
        return $this->numberOfStars;
    }

    public function isPinned(): bool
    {
        return $this->pinned;
    }
}
