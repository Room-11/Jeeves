<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

class EditMessage implements Message, UserMessage
{
    private $id;

    private $actionId;

    private $userId;

    private $username;

    private $roomId;

    private $content;

    private $numberOfEdits = 0;

    private $timestamp;

    public function __construct(array $data)
    {
        $this->id            = $data['message_id'];
        $this->actionId      = $data['id'];
        $this->userId        = $data['user_id'];
        $this->username      = $data['user_name'];
        $this->roomId        = $data['room_id'];
        $this->content       = $data['content'];
        $this->numberOfEdits = $data['message_edits'];
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

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getRoomId(): int
    {
        return $this->roomId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getNumberOfEdits(): int
    {
        return $this->numberOfEdits;
    }

    public function getTimestamp(): \DateTime
    {
        return $this->timestamp;
    }
}
