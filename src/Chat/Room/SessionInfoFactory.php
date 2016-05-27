<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

class SessionInfoFactory
{
    public function build(int $userId, string $fkey, string $mainSiteUrl, string $webSocketUrl)
    {
        return new SessionInfo($userId, $fkey, $mainSiteUrl, $webSocketUrl);
    }
}
