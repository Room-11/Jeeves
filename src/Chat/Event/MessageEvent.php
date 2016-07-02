<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Event\Traits\RoomSource;
use Room11\Jeeves\Chat\Event\Traits\UserSource;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use function Room11\DOMUtils\domdocument_load_html;

abstract class MessageEvent extends BaseEvent implements UserSourcedEvent, RoomSourcedEvent
{
    use RoomSource;
    use UserSource;

    /**
     * @var int
     */
    private $messageId;

    /**
     * @var \DOMDocument
     */
    private $messageContent;

    protected function __construct(array $data, ChatRoom $room)
    {
        parent::__construct($data);

        $this->room = $room;

        $this->userId = (int)$data['user_id'];
        $this->userName = (string)$data['user_name'];

        $this->messageId = (int)$data['message_id'];
        $this->messageContent = domdocument_load_html((string)($data['content'] ?? ''), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    }

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function getMessageContent(): \DOMDocument
    {
        return $this->messageContent;
    }
}
