<?php declare(strict_types = 1);

namespace Room11\Jeeves\System;

class BuiltInCommandInfo
{
    private $command;
    private $description;
    private $adminOnly;

    public function __construct(string $command, string $description, bool $adminOnly = false)
    {
        $this->command = $command;
        $this->description = $description;
        $this->adminOnly = $adminOnly;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isAdminOnly(): bool
    {
        return $this->adminOnly;
    }
}
