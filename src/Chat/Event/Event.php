<?php  declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\WebSocket\Handler as WebSocketHandler;

interface Event
{
    public function getTypeId(): int;

    public function getId(): int;

    public function getTimestamp(): \DateTimeImmutable;

    public function getSourceHandler(): WebSocketHandler;
}
