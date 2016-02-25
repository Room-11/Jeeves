<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage;

interface Admin
{
    public function getAll(): \Generator;

    public function isAdmin(int $userId): \Generator;

    public function add(int $userId): \Generator;

    public function remove(int $userId): \Generator;
}
