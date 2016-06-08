<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

class SessionInfo
{
    private $userId;
    private $fkey;
    private $mainSiteUrl;
    private $webSocketUrl;

    public function __construct(int $userId, string $fkey, string $mainSiteUrl, string $webSocketUrl)
    {
        $this->userId = $userId;
        $this->fkey = $fkey;
        $this->mainSiteUrl = $mainSiteUrl;
        $this->webSocketUrl = $webSocketUrl;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getFKey(): string
    {
        return $this->fkey;
    }

    public function getMainSiteUrl(): string
    {
        return $this->mainSiteUrl;
    }

    public function getWebSocketUrl(): string
    {
        return $this->webSocketUrl;
    }
}
