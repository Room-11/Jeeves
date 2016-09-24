<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

use Amp\Websocket\Endpoint as WebsocketEndpoint;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;

class Room
{
    private $identifier;
    private $sessionInfo;
    private $websocketEndpoint;
    private $keyValueStore;

    private static $endpointURLTemplates = [
        Endpoint::MAINSITE_USER => '%1$s/users/%2$d',
    ];

    public function __construct(
        Identifier $identifier,
        SessionInfo $sessionInfo,
        WebsocketEndpoint $websocketEndpoint,
        KeyValueStore $keyValueStore
    ) {
        $this->identifier = $identifier;
        $this->sessionInfo = $sessionInfo;
        $this->websocketEndpoint = $websocketEndpoint;
        $this->keyValueStore = $keyValueStore;
    }

    public function getIdentifier(): Identifier
    {
        return $this->identifier;
    }

    public function getSessionInfo(): SessionInfo
    {
        return $this->sessionInfo;
    }

    public function getWebsocketEndpoint(): WebsocketEndpoint
    {
        return $this->websocketEndpoint;
    }

    public function getKeyValueStore(): KeyValueStore
    {
        return $this->keyValueStore;
    }

    public function getEndpointURL(int $endpoint, ...$extraData): string
    {
        if ($endpoint < 500) {
            return $this->identifier->getEndpointURL($endpoint, ...$extraData);
        }

        if (!isset(self::$endpointURLTemplates[$endpoint])) {
            throw new \LogicException('Invalid endpoint ID');
        }

        return sprintf(
            self::$endpointURLTemplates[$endpoint],
            rtrim($this->sessionInfo->getMainSiteUrl(), '/'),
            ...$extraData
        );
    }

    public function __debugInfo()
    {
        return [
            'identifier' => $this->identifier,
            'sessionInfo' => $this->sessionInfo,
            'websocketEndpoint' => $this->websocketEndpoint->getInfo(),
        ];
    }
}
