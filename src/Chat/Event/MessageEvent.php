<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Event\Traits\RoomSource;
use Room11\Jeeves\Chat\Event\Traits\UserSource;

abstract class MessageEvent extends Event implements UserSourcedEvent, RoomSourcedEvent
{
    use RoomSource;
    use UserSource;

    private $messageId;

    private $messageContent;

    protected function __construct(array $data)
    {
        parent::__construct((int)$data['id'], (int)$data['time_stamp']);

        $this->roomId = (int)$data['room_id'];

        $this->userId = (int)$data['user_id'];
        $this->userName = (string)$data['user_name'];

        $this->messageId = (int)$data['message_id'];
        $this->messageContent = $data['content'] ?? '';
    }

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function getMessageContent(): string
    {
        return $this->messageContent;
    }
}
