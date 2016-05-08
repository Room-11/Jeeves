<?php

namespace Room11\Jeeves\Chat\Event;

interface Event
{
    public function getTypeId(): int;

    public function getEventId(): int;

    public function getTimestamp(): \DateTime;
}
