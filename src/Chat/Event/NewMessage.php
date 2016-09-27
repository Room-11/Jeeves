<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

class NewMessage extends MessageEvent
{
    const TYPE_ID = EventType::MESSAGE_POSTED;
}
