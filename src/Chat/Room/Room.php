<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

class Room {
    private $id;
    private $host;

    public function __construct(int $id, Host $host) {
        $this->id = $id;
        $this->host = $host;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getHost(): Host {
        return $this->host;
    }
}
