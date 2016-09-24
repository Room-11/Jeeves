<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Event\Factory as EventFactory;
use Room11\Jeeves\Chat\WebSocket\Handler as WebSocketHandler;

class Builder
{
    private $eventFactory;
    private $chatClient;

    public function __construct(EventFactory $eventFactory, ChatClient $chatClient)
    {
        $this->eventFactory = $eventFactory;
        $this->chatClient = $chatClient;
    }

    public function build(array $data, WebSocketHandler $handler): \Generator
    {
        $result = [];
        
        $room = $handler->getRoom();
        $roomId = $room->getIdentifier()->getId();

        foreach ($data['r' . $roomId]['e'] ?? [] as $eventData) {
            if (!isset($eventData['id'])) {
                continue;
            }

            $eventId = (int)$eventData['id'];
            if (isset($result[$eventId])) {
                continue;
            }

            $event = $this->eventFactory->build((int)($eventData['event_type'] ?? 0), $eventData, $handler);

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
