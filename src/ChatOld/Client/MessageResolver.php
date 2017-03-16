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

    public function resolveMessageIDFromPermalink(string $text, string $domain = null): int
    {
        static $exprTemplates = [
            '~\bhttps?://%s/transcript/message/(\d+)(?:#\1)\b~i',
            '~\bhttps?://%s/transcript/\d+\?m=(\d+)(?:#\1)\b~i',
        ];

        foreach ($exprTemplates as $exprTemplate) {
            $expr = sprintf($exprTemplate, $domain ? preg_quote($domain) : DNS_NAME_EXPR);

            if (preg_match($expr, $text, $match)) {
                return (int)$match[1];
            }
        }

        throw new MessageIDNotFoundException;
    }

    public function resolveMessageText(Room $room, string $text, int $flags = self::MATCH_ANY): Promise
    {
        if (preg_match('~^:\d+\s+(.+)~', $text, $match)) {
            $text = $match[1];
        }

        return resolve(function() use($room, $text, $flags) {
            if ($flags & self::MATCH_PERMALINKS) {
                try {
                    $messageID = $this->resolveMessageIDFromPermalink($text);
                    $text = yield $this->chatClient->getMessageText($room, $messageID);

                    return ($flags & self::RECURSE)
                        ? $this->resolveMessageText($room, $text, $flags | self::MATCH_LITERAL_TEXT)
                        : $text;
                } catch (MessageIDNotFoundException $e) { /* ignore, there may be other matches */ }
            }

            if (($flags & self::MATCH_MESSAGE_IDS) && ctype_digit($text)) {
                $text = yield $this->chatClient->getMessageText($room, (int)$text);

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
