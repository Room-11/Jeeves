<?php declare(strict_types = 1);

namespace Room11\Jeeves;

class VersionIdentifier
{
    private $versionString;
    private $lastTag;
    private $commitsSinceTag;
    private $commitHash;

    public function __construct(string $versionString, string $lastTag, int $commitsSinceTag, ?string $commitHash)
    {
        $this->versionString = $versionString;
        $this->lastTag = $lastTag;
        $this->commitsSinceTag = $commitsSinceTag;
        $this->commitHash = $commitHash;
    }

    public function getVersionString(): string
    {
        return $this->versionString;
    }

    public function getCommitsSinceTag(): int
    {
        return $this->commitsSinceTag;
    }

    public function getCommitHash(): string
    {
        return $this->commitHash;
    }

    public function getLastTag(): string
    {
        return $this->lastTag;
    }

    public function getGithubUrl(): string
    {
        $path = $this->commitHash !== null
            ? '/commit/' . $this->commitHash
            : '/tree/' . $this->lastTag;

        return GITHUB_PROJECT_URL . $path;
    }
}
