<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Amp\Promise;
use Room11\Jeeves\Chat\Room\Room;
use function Amp\resolve;
use const Room11\Jeeves\DNS_NAME_EXPR;

class MessageResolver
{
    const MATCH_PERMALINKS     = 0b0001;
    const MATCH_MESSAGE_IDS    = 0b0010;
    const MATCH_LITERAL_TEXT   = 0b0100;
    const RECURSE              = 0b1000;
    const MATCH_ANY            = 0b1111;

    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function resolveMessageText(Room $room, string $text, int $flags = self::MATCH_ANY): Promise
    {
        if (preg_match('~^:\d+\s+(.+)~', $text, $match)) {
            $text = $match[1];
        }

        return resolve(function() use($room, $text, $flags) {
            if ($flags & self::MATCH_PERMALINKS) {
                $exprs = [
                    '~\bhttps?://' . DNS_NAME_EXPR . '/transcript/message/(\d+)(?:#\1)\b~i',
                    '~\bhttps?://' . DNS_NAME_EXPR . '/transcript/\d+\?m=(\d+)(?:#\1)\b~i',
                ];

                foreach ($exprs as $expr) {
                    if (preg_match($expr, $text, $match)) {
                        $text = yield $this->chatClient->getMessageText($room, (int)$match[1]);

                        return ($flags & self::RECURSE)
                            ? $this->resolveMessageText($room, $text, $flags | self::MATCH_LITERAL_TEXT)
                            : $text;
                    }
                }
            }

            if (($flags & self::MATCH_MESSAGE_IDS) && preg_match('~^(\d+)$~', $text, $match)) {
                $text = yield $this->chatClient->getMessageText($room, (int)$match[1]);

                return ($flags & self::RECURSE)
                    ? $this->resolveMessageText($room, $text, $flags | self::MATCH_LITERAL_TEXT)
                    : $text;
            }

            if (($flags & self::MATCH_LITERAL_TEXT)) {
                return $text;
            }

            throw new MessageFetchFailureException;
        });
    }
}
