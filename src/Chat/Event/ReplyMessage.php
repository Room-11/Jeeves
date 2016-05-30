<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class ReplyMessage extends MentionMessage
{
    const TYPE_ID = 18;

    private $showParent;

    public function __construct(array $data, ChatRoom $room)
    {
        parent::__construct($data, $room);

        $this->showParent = $data['show_parent'] ?? false;
    }

    public function shouldShowParent(): bool
    {
        return $this->showParent;
    }
}
