<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

use const Room11\Jeeves\ROOM_IDENTIFIER_EXPR;

class IdentifierFactory
{
    public function createFromIdentString(string $ident): Identifier
    {
        if (!preg_match('/^' . ROOM_IDENTIFIER_EXPR . '$/i', $ident, $matches)) {
            throw new \InvalidArgumentException('Invalid ident string: ' . $ident);
        }

        return new Identifier((int)$matches[2], $matches[1]);
    }

    public function create(int $id, string $host): Identifier
    {
        return new Identifier($id, $host);
    }
}
