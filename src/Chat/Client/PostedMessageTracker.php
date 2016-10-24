<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Room11\Jeeves\Chat\Room\InvalidRoomIdentifierException;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use const Room11\Jeeves\ROOM_IDENTIFIER_EXPR;

class PostedMessageTracker
{
    private $messages = [];

    private function normalizeKey($key): string
    {
        if (is_object($key)) {
            if ($key instanceof ChatRoom) {
                $key = $key->getIdentifier();
            }

            if ($key instanceof ChatRoomIdentifier) {
                return $key->getIdentString();
            }
        } else if (is_string($key)) {
            if (!preg_match('/^' . ROOM_IDENTIFIER_EXPR . '$/i', $key)) {
                throw new InvalidRoomIdentifierException('Invalid identifier string format');
            }

            return $key;
        }

        throw new InvalidRoomIdentifierException('Identifier must be a string or identifiable object');
    }

    public function setLastPostedMessage($room, string $message)
    {
        $this->messages[$this->normalizeKey($room)] = $message;
    }

    public function getLastPostedMessage($room): string
    {
        return $this->messages[$this->normalizeKey($room)] ?? '';
    }
}
