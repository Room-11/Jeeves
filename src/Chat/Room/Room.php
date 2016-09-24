<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

use Amp\Promise;
use function Amp\resolve;
use Amp\Success;
use Amp\Websocket\Endpoint as WebsocketEndpoint;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\Storage\Room as RoomStorage;

class Room
{
    private $identifier;
    private $sessionInfo;
    private $roomStorage;
    private $permanent;
    private $websocketEndpoint;
    private $keyValueStore;

    /**
     * @var bool
     */
    private $approved = false;

    private static $endpointURLTemplates = [
        Endpoint::MAINSITE_USER => '%1$s/users/%2$d',
    ];

    public function __construct(
        Identifier $identifier,
        SessionInfo $sessionInfo,
        RoomStorage $roomStorage,
        bool $permanent,
        WebsocketEndpoint $websocketEndpoint,
        KeyValueStore $keyValueStore
    ) {
        $this->identifier = $identifier;
        $this->sessionInfo = $sessionInfo;
        $this->websocketEndpoint = $websocketEndpoint;
        $this->keyValueStore = $keyValueStore;
        $this->roomStorage = $roomStorage;
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

    public function isApproved(): Promise
    {
        if ($this->permanent || $this->approved) {
            return new Success(true);
        }

        return resolve(function() {
            return $this->approved = yield $this->roomStorage->isApproved($this->identifier);
        });
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
