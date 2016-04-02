<?php declare(strict_types=1);

namespace Room11\Jeeves\Storage;

interface CustomCommand
{
    public function getAll(): \Generator;

    public function exists(string $command): \Generator;

    public function add(string $command, string $reply): \Generator;

    public function remove(string $command): \Generator;
}
