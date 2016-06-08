<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

use Room11\Jeeves\Chat\Client\Entities\User;

class SessionInfoFactory
{
    public function build(User $user, string $fkey, string $mainSiteUrl, string $webSocketUrl)
    {
        return new SessionInfo($user, $fkey, $mainSiteUrl, $webSocketUrl);
    }
}
