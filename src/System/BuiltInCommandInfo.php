<?php declare(strict_types = 1);

namespace Room11\Jeeves\System;

class BuiltInCommandInfo
{
    const REQUIRE_ADMIN_USER = 0b01;
    const ALLOW_UNAPPROVED_ROOM = 0b10;

    private $command;
    private $description;
    private $flags;

    public function __construct(string $command, string $description, int $flags = 0)
    {
        $this->command = $command;
        $this->description = $description;
        $this->flags = $flags;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function requiresAdminUser(): bool
    {
        return (bool)($this->flags & self::REQUIRE_ADMIN_USER);
    }

    public function requiresApprovedRoom(): bool
    {
        return !($this->flags & self::ALLOW_UNAPPROVED_ROOM);
    }
}
