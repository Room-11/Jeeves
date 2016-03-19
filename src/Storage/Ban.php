<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage;

interface Ban
{
    public function getAll(): \Generator;

    public function isBanned(int $userId): \Generator;

    public function add(int $userId, string $duration): \Generator;

    public function remove(int $userId): \Generator;
}
