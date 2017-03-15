<?php  declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

interface Event
{
    function getTypeId(): int;

    function getId(): int;

    function getTimestamp(): \DateTimeImmutable;

    function getHost(): string;
}
