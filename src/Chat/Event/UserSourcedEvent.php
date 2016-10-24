<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

interface UserSourcedEvent extends Event
{
    public function getUserId(): int;
    public function getUserName(): string;
}
