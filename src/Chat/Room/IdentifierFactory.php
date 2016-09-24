<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

class IdentifierFactory
{
    public function create(int $id, string $host, bool $isSecure): Identifier
    {
        return new Identifier($id, $host, $isSecure);
    }
}
