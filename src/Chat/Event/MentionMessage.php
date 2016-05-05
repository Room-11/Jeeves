<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Message\Factory as MessageFactory;

class MentionMessage extends MessageEvent
{
    const EVENT_TYPE_ID = 8;

    private $parentId;

    public function __construct(array $data, MessageFactory $messageFactory, string $host)
    {
        parent::__construct($data, $messageFactory, $host);

        $this->parentId = $data['parent_id'];
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }
}
