<?php

namespace Room11\Jeeves\Chat\Event;

class Unknown extends Event
{
    const EVENT_TYPE_ID = 0;

    private $data;

    public function __construct(array $data)
    {
        parent::__construct((int)($data['id'] ?? 0), (int)($data['time_stamp'] ?? 0));

        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
