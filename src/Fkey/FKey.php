<?php

namespace Room11\Jeeves\Fkey;

class FKey {
    private $value;

    public function __construct(string $value) {
        $this->value = $value;
    }

    public function __toString(): string {
        return $this->value;
    }
}
