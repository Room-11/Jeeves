<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Room11\Jeeves\Chat\Event\MentionMessage;
use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Room\Collection as ChatRoomCollection;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class Factory
{
    private $chatRooms;

    public function __construct(ChatRoomCollection $chatRooms)
    {
        $this->chatRooms = $chatRooms;
    }

    public function build(MessageEvent $event, ChatRoom $room): Message
    {
        if (strpos($event->getMessageContent(), '!!') === 0) {
            return new Command($event, $room);
        }

        if ($event instanceof MentionMessage) {
            return new Conversation($event, $room);
        }

        return new Message($event, $room);
    }
}
