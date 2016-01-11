<?php

namespace Room11\Jeeves\Chat\Message;

class Unknown implements Message
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }
}
