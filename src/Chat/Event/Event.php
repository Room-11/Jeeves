<?php  declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

interface Event
{
    public function getTypeId(): int;

    public function getId(): int;

    public function getTimestamp(): \DateTimeImmutable;

    public function getHost(): string;
}
