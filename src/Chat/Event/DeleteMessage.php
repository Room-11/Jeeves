<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Message\Factory as MessageFactory;

class DeleteMessage extends MessageEvent
{
    const EVENT_TYPE_ID = 10;

    public function __construct(array $data, MessageFactory $messageFactory)
    {
        parent::__construct($data, $messageFactory);
    }
}
