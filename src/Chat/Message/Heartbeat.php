<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

class Heartbeat implements Message
{
    private $roomId;

    private $lastActionId;

    // no idea what this is (yet)
    private $d;

    public function __construct(array $data)
    {
        $this->roomId        = reset($data);
        $this->lastActionId  = $data['t'] ?? null;
        $this->d             = $data['d'] ?? null;
    }

    public function getRoomId(): int
    {
        return $this->roomId;
    }

    public function getLastActionId()
    {
        return $this->lastActionId;
    }

    public function getD()
    {
        return $this->d;
    }
}
