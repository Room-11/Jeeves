<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class ReplyMessage extends MessageEvent
{
    const TYPE_ID = 18;

    private $parentId;
    private $showParent;

    public function __construct(array $data, ChatRoom $room)
    {
        parent::__construct($data, $room);

        $this->parentId = $data['parent_id'] ?? 0;
        $this->showParent = $data['show_parent'] ?? false;
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }

    public function shouldShowParent(): bool
    {
        return $this->showParent;
    }
}
