<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

class NewMessage implements Message
{
    private $id;

    private $userId;

    private $username;

    private $roomId;

    private $content;

    private $timestamp;

    public function __construct(array $data)
    {
        $this->id       = $data['id'];
        $this->userId   = $data['user_id'];
        $this->username = $data['user_name'];
        $this->roomId   = $data['room_id'];
        $this->content  = $data['content'];
        $this->timestamp = new \DateTime('@' . $data['time_stamp']);
    }

    public function getId(): int
    {
        return $this->id;
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

    public function getTimestamp(): \DateTime
    {
        return $this->timestamp;
    }
}
