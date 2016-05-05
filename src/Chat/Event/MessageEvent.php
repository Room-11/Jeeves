<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Event\Traits\RoomSource;
use Room11\Jeeves\Chat\Event\Traits\UserSource;
use Room11\Jeeves\Chat\Message\Factory as MessageFactory;
use Room11\Jeeves\Chat\Message\Message;

abstract class MessageEvent extends Event implements UserSourcedEvent, RoomSourcedEvent
{
    use RoomSource;
    use UserSource;

    /**
     * @var int
     */
    private $messageId;

    /**
     * @var string
     */
    private $messageContent;

    /**
     * @var Message
     */
    private $message;

    protected function __construct(array $data, MessageFactory $messageFactory, string $host)
    {
        parent::__construct((int)$data['id'], (int)$data['time_stamp']);

        $this->roomId = (int)$data['room_id'];

        $this->userId = (int)$data['user_id'];
        $this->userName = (string)$data['user_name'];

        $this->messageId = (int)$data['message_id'];
        $this->messageContent = (string)$data['content'] ?? '';

        $this->message = $messageFactory->build($this, $host);
    }

    public function getMessage(): Message
    {
        return $this->message;
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
