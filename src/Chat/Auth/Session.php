<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Auth;

use Room11\Jeeves\Chat\Entities\ChatUser;

class Session
{
    private $user;
    private $fkey;
    private $mainSiteUrl;
    private $webSocketUrl;

    public function __construct(ChatUser $user, string $fkey, string $mainSiteUrl, string $webSocketUrl)
    {
        $this->user = $user;
        $this->fkey = $fkey;
        $this->mainSiteUrl = $mainSiteUrl;
        $this->webSocketUrl = $webSocketUrl;
    }

    public function getUser(): ChatUser
    {
        return $this->user;
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
