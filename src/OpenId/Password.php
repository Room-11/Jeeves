<?php

namespace Room11\Jeeves\OpenId;

class Password {
    private $value;

    public function __construct(string $value) {
        $this->value = $value;
    }

    public function __toString() {
        return $this->value;
    }
}