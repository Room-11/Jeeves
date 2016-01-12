<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

class Unknown implements Message
{
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
