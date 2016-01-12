<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

class StarMessage implements Message
{
    private $id;

    private $actionId;

    private $roomId;

    private $messageId;

    private $content;

    private $numberOfStars;

    private $pinned;

    private $timestamp;

    public function __construct(array $data)
    {
        $this->id            = $data['message_id'];
        $this->actionId      = $data['id'];
        $this->roomId        = $data['room_id'];
        $this->messageId     = $data['message_id'];
        $this->content       = $data['content'];
        $this->numberOfStars = $data['message_stars'] ?? 0;
        $this->pinned        = isset($data['message_owner_stars']);
        $this->timestamp     = new \DateTime('@' . $data['time_stamp']);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getActionId(): int
    {
        return $this->actionId;
    }

    public function getRoomId(): int
    {
        return $this->roomId;
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

    public function getTimestamp(): \DateTime
    {
        return $this->timestamp;
    }
}
