<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\Room;

class Identifier
{
    private $id;
    private $host;
    private $isSecure;

    private static $endpointURLTemplates = [
        Endpoint::UI => '%1$s://%2$s/rooms/%3$d',
        Endpoint::WEBSOCKET_AUTH => '%1$s://%2$s/ws-auth',
        Endpoint::EVENT_HISTORY => '%1$s://%2$s/chats/%3$d/events',
        Endpoint::GET_MESSAGE => '%1$s://%2$s/message/%4$d',
        Endpoint::POST_MESSAGE => '%1$s://%2$s/chats/%3$d/messages/new',
        Endpoint::EDIT_MESSAGE => '%1$s://%2$s/messages/%4$d',
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

    public function getEndpointURL(int $endpoint, ...$extraData): string {
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
}
