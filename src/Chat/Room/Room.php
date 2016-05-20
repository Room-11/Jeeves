<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

class Room
{
    private $identifier;
    private $fKey;
    private $mainSiteURL;
    private $webSocketURL;

    private static $endpointURLTemplates = [
        Endpoint::MAINSITE_USER => '%1$s/users/%2$d',
    ];

    public function __construct(Identifier $identifier, string $fKey, string $webSocketURL, string $mainSiteURL)
    {
        $this->identifier = $identifier;
        $this->fKey = $fKey;
        $this->mainSiteURL = $mainSiteURL;
        $this->webSocketURL = $webSocketURL;
    }

    public function getIdentifier(): Identifier
    {
        return $this->identifier;
    }

    public function getFKey(): string
    {
        return $this->fKey;
    }

    public function getMainSiteURL(): string
    {
        return $this->mainSiteURL;
    }

    public function getWebSocketURL(): string
    {
        return $this->webSocketURL;
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
            rtrim($this->mainSiteURL, '/'),
            ...$extraData
        );
    }
}
