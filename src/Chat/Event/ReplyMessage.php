<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Event;

class ReplyMessage extends MessageEvent
{
    const TYPE_ID = EventType::MESSAGE_REPLY;
}
