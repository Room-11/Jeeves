<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Ds\Deque;
use Room11\Jeeves\Chat\Entities\PostedMessage;
use Room11\Jeeves\Chat\Room\InvalidRoomIdentifierException;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use const Room11\Jeeves\ROOM_IDENTIFIER_EXPR;

class PostedMessageTracker
{
    const BUFFER_SIZE = 20;

    /**
     * @var Deque[]
     */
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

    public function pushMessage(PostedMessage $message)
    {
        $ident = $message->getRoom()->getIdentifier()->getId();

        if (!isset($this->messages[$ident])) {
            $this->messages[$ident] = new Deque;
        }

        $this->messages[$ident]->push($message);

        if ($this->messages[$ident]->count() > self::BUFFER_SIZE) {
            $this->messages[$ident]->shift();
        }
    }

    public function popMessage(ChatRoom $room): PostedMessage
    {
        $ident = $this->normalizeKey($room);

        if (!isset($this->messages[$ident]) || !$this->messages[$ident] instanceof Deque) {
            return null;
        }

        $message = $this->messages[$ident]->pop();

        if ($this->messages[$ident]->isEmpty()) {
            unset($this->messages[$ident]);
        }

        return $message;
    }

    public function peekMessage($room): PostedMessage
    {
        $ident = $this->normalizeKey($room);

        return !isset($this->messages[$ident])
            ? $this->messages[$ident]->last()
            : null;
    }

    public function getAll($room): array
    {
        $ident = $this->normalizeKey($room);

        return !isset($this->messages[$ident])
            ? $this->messages[$ident]->toArray()
            : [];
    }
}
