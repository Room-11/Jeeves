<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

use Room11\Jeeves\Fkey\FKey;

class Room
{
    private $identifier;
    private $fKey;
    private $mainSiteURL;
    private $webSocketURL;

    public function __construct(RoomIdentifier $identifier, FKey $fKey, string $webSocketURL, string $mainSiteURL)
    {
        $this->identifier = $identifier;
        $this->fKey = $fKey;
        $this->mainSiteURL = $mainSiteURL;
        $this->webSocketURL = $webSocketURL;
    }

    public function getIdentifier(): RoomIdentifier
    {
        return $this->identifier;
    }

    public function getFKey(): FKey
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
}
