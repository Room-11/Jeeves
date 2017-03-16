<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event\Traits;

trait UserSource
{
    private $userId;

    private $userName;

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }
}
