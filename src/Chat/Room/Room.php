<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

use Amp\Promise;
use Amp\Success;
use Amp\Websocket\Endpoint as WebsocketEndpoint;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;

class Room
{
    private $identifier;
    private $sessionInfo;
    private $presenceManager;
    private $permanent;
    private $websocketEndpoint;
    private $keyValueStore;

    public function __construct(
        Identifier $identifier,
        SessionInfo $sessionInfo,
        PresenceManager $presenceManager,
        bool $permanent,
        WebsocketEndpoint $websocketEndpoint,
        KeyValueStore $keyValueStore
    ) {
        $this->identifier = $identifier;
        $this->sessionInfo = $sessionInfo;
        $this->websocketEndpoint = $websocketEndpoint;
        $this->keyValueStore = $keyValueStore;
        $this->presenceManager = $presenceManager;
        $this->permanent = $permanent;
    }

    public function getIdentifier(): Identifier
    {
        return $this->identifier;
    }

    public function getSessionInfo(): SessionInfo
    {
        return $this->sessionInfo;
    }

    public function isPermanent(): bool
    {
        return $this->permanent;
    }

    public function getWebsocketEndpoint(): WebsocketEndpoint
    {
        return $this->websocketEndpoint;
    }

    public function getKeyValueStore(): KeyValueStore
    {
        return $this->keyValueStore;
    }

    public function isApproved(): Promise
    {
        return $this->permanent
            ? new Success(true)
            : $this->presenceManager->isApproved($this->identifier);
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
