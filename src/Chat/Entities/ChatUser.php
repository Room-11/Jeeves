<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Entities;

class ChatUser
{
    const GRAVATAR_URL_TEMPLATE = 'https://www.gravatar.com/avatar/%s?s=256&d=identicon&r=PG';

    private $id;
    private $name;
    private $avatarUrl;
    private $reputation;
    private $isMod;
    private $lastPost;
    private $lastSeen;

    public function __construct(array $data)
    {
        $this->id = (int)($data['id'] ?? 0);
        $this->name = (string)($data['name'] ?? '');
        $this->reputation = (int)($data['reputation'] ?? 0);
        $this->isMod = (bool)($data['is_moderator'] ?? false);
        $this->lastPost = new \DateTimeImmutable('@' . ($data['last_post'] ?? 0));
        $this->lastSeen = new \DateTimeImmutable('@' . ($data['last_seen'] ?? 0));

        $emailHash = (string)($data['email_hash'] ?? '');
        $this->avatarUrl = ('!' === $emailHash[0] ?? '')
            ? substr($emailHash, 1)
            : sprintf(self::GRAVATAR_URL_TEMPLATE, $emailHash);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAvatarUrl(): string
    {
        return $this->avatarUrl;
    }

    public function getReputation(): int
    {
        return $this->reputation;
    }

    public function isModerator(): bool
    {
        return $this->isMod;
    }

    public function getLastPost(): \DateTimeImmutable
    {
        return $this->lastPost;
    }

    public function getLastSeen(): \DateTimeImmutable
    {
        return $this->lastSeen;
    }
}
