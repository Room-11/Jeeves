<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

interface UserSourcedEvent
{
    public function getUserId(): int;
    public function getUserName(): string;
}
