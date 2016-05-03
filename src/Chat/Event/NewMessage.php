<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

class NewMessage extends MessageEvent
{
    const EVENT_TYPE_ID = 1;

    public function __construct(array $data)
    {
        parent::__construct($data);
    }
}
