<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\Room;

class Identifier
{
    private $id;
    private $host;
    private $isSecure;

    private static $endpointURLTemplates = [
        Endpoint::CHATROOM_UI                 => '%1$s://%2$s/rooms/%3$d',
        Endpoint::CHATROOM_WEBSOCKET_AUTH     => '%1$s://%2$s/ws-auth',
        Endpoint::CHATROOM_EVENT_HISTORY      => '%1$s://%2$s/chats/%3$d/events',
        Endpoint::CHATROOM_STARS_LIST         => '%1$s://%2$s/chats/stars/%3$d?count=0',

        Endpoint::CHATROOM_GET_MESSAGE        => '%1$s://%2$s/message/%4$d',
        Endpoint::CHATROOM_POST_MESSAGE       => '%1$s://%2$s/chats/%3$d/messages/new',
        Endpoint::CHATROOM_EDIT_MESSAGE       => '%1$s://%2$s/messages/%4$d',
        Endpoint::CHATROOM_PIN_MESSAGE        => '%1$s://%2$s/messages/%4$d/owner-star',
        Endpoint::CHATROOM_UNSTAR_MESSAGE     => '%1$s://%2$s/messages/%4$d/unstar',

        Endpoint::CHATROOM_INFO_GENERAL       => '%1$s://%2$s/rooms/info/%3$d?tab=general',
        Endpoint::CHATROOM_INFO_STARS         => '%1$s://%2$s/rooms/info/%3$d?tab=stars',
        Endpoint::CHATROOM_INFO_CONVERSATIONS => '%1$s://%2$s/rooms/info/%3$d?tab=conversations',
        Endpoint::CHATROOM_INFO_SCHEDULE      => '%1$s://%2$s/rooms/info/%3$d?tab=schedule',
        Endpoint::CHATROOM_INFO_FEEDS         => '%1$s://%2$s/rooms/info/%3$d?tab=feeds',
        Endpoint::CHATROOM_INFO_ACCESS        => '%1$s://%2$s/rooms/info/%3$d?tab=access',
        Endpoint::CHAT_USER                   => '%1$s://%2$s/users/%4$d',
    ];

    public function __construct(int $id, string $host, bool $isSecure) {
        $this->id = $id;
        $this->host = strtolower($host);
        $this->isSecure = $isSecure;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getHost(): string {
        return $this->host;
    }

    public function getIdentString(): string {
        return $this->host . '#' . $this->id;
    }

    public function isSecure(): bool {
        return $this->isSecure;
    }

    public function getEndpointUrl(int $endpoint, ...$extraData): string {
        if (!isset(self::$endpointURLTemplates[$endpoint])) {
            throw new \LogicException('Invalid endpoint ID');
        }

        return sprintf(
            self::$endpointURLTemplates[$endpoint],
            $this->isSecure ? "https" : "http",
            $this->host,
            $this->id,
            ...$extraData
        );
    }

    public function getOriginURL(string $protocol): string {
        return sprintf('%s://%s', $this->isSecure ? $protocol . 's' : $protocol, $this->host);
    }

    public function __toString()
    {
        return $this->getIdentString();
    }
}
