<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\WebSocket\Handler as WebSocketHandler;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;

class Room
{
    private $identifier;
    private $sessionInfo;
    private $presenceManager;
    private $permanent;
    private $websocketHandler;
    private $keyValueStore;

    public function __construct(
        Identifier $identifier,
        SessionInfo $sessionInfo,
        WebSocketHandler $websocketHandler,
        PresenceManager $presenceManager,
        KeyValueStore $keyValueStore,
        bool $permanent
    ) {
        $this->identifier = $identifier;
        $this->sessionInfo = $sessionInfo;
        $this->websocketHandler = $websocketHandler;
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

    public function getWebsocketHandler(): WebSocketHandler
    {
        return $this->websocketHandler;
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
            'websocketEndpoint' => $this->websocketHandler->getEndpoint()->getInfo(),
        ];
    }
}
