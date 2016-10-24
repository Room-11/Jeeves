<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Auth;

use Room11\Jeeves\Chat\Entities\ChatUser;

class SessionFactory
{
    public function build(ChatUser $user, string $fkey, string $mainSiteUrl, string $webSocketUrl)
    {
        return new Session($user, $fkey, $mainSiteUrl, $webSocketUrl);
    }
}
