<?php

namespace Room11\Jeeves\Chat\Message;

class Factory
{
    public function build($data)
    {
        yield new Unknown($data);
    }
}
