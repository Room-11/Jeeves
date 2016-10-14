<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Event\Factory as EventFactory;
use Room11\Jeeves\Chat\Room\ConnectedRoomCollection;
use Room11\Jeeves\Chat\Room\Identifier;

class Builder
{
    private $eventFactory;
    private $chatClient;
    private $connectedRooms;

    public function __construct(EventFactory $eventFactory, ChatClient $chatClient, ConnectedRoomCollection $connectedRooms)
    {
        $this->eventFactory = $eventFactory;
        $this->chatClient = $chatClient;
        $this->connectedRooms = $connectedRooms;
    }

    public function build(array $data, Identifier $identifier)
    {
        $result = [];

        $room = $this->connectedRooms->get($identifier);
        $roomId = $identifier->getId();

        foreach ($data['r' . $roomId]['e'] ?? [] as $eventData) {
            if (!isset($eventData['id'])) {
                continue;
            }

            $eventId = (int)$eventData['id'];
            if (isset($result[$eventId])) {
                continue;
            }

            $event = $this->eventFactory->build((int)($eventData['event_type'] ?? 0), $eventData, $room);

            if ($event instanceof RoomSourcedEvent && $eventData['room_id'] !== $roomId) {
                continue;
            }

            if ($event instanceof MessageEvent) {
                $isPartial = (new \DOMXPath($event->getMessageContent()))
                    ->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' partial ')]")
                    ->length;

                if ($isPartial) {
                    $eventData['content'] = nl2br(htmlentities(yield $this->chatClient->getMessageText($room, $event->getMessageId())));
                    $event = $this->eventFactory->build((int)($eventData['event_type'] ?? 0), $eventData, $room);
                }
            }

            $result[$eventId] = $event;
        }

        return $result;
    }
}
