<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Event\Traits\RoomSource;

class StarMessage extends Event implements RoomSourcedEvent
{
    use RoomSource;

    const EVENT_TYPE_ID = 6;

    private $messageId;

    private $content;

    private $numberOfStars;

    private $pinned;

    public function __construct(array $data)
    {
        parent::__construct((int)$data['id'], (int)$data['time_stamp']);

        $this->roomId        = $data['room_id'];

        $this->messageId        = $data['message_id'];
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
