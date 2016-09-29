<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Auth;

use Room11\Jeeves\Chat\Entities\User;

class SessionFactory
{
    public function build(User $user, string $fkey, string $mainSiteUrl, string $webSocketUrl)
    {
        return new Session($user, $fkey, $mainSiteUrl, $webSocketUrl);
    }
}
