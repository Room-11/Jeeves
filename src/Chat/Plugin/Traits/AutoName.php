<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Plugin\Traits;

trait AutoName
{
    public function getName(): string
    {
        return basename(get_class($this));
    }
}
