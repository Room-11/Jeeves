<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\Room;

use const Room11\Jeeves\ROOM_IDENTIFIER_EXPR;

class Identifier
{
    private $id;
    private $host;
    private $isSecure;

    public static function createFromIdentString(string $ident, bool $isSecure): Identifier
    {
        if (!preg_match('/^' . ROOM_IDENTIFIER_EXPR . '$/i', $ident, $matches)) {
            throw new \InvalidArgumentException('Invalid ident string: ' . $ident);
        }

        return new self((int)$matches[2], $matches[1], $isSecure);
    }

    public function __construct(int $id, string $host, bool $isSecure) {
        $this->id = $id;
        $this->host = strtolower($host);
        $this->isSecure = $isSecure;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getHost(): string {
        return $this->host;
    }

    public function getIdentString(): string {
        return $this->host . '#' . $this->id;
    }

    public function isSecure(): bool {
        return $this->isSecure;
    }

    public function getOriginURL(string $protocol): string {
        return sprintf('%s://%s', $this->isSecure ? $protocol . 's' : $protocol, $this->host);
    }

    public function __toString()
    {
        return $this->getIdentString();
    }
}
