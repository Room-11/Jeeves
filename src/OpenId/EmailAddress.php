<?php

namespace Room11\Jeeves\OpenId;

class EmailAddress {
    private $value;

    public function __construct(string $value) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailAddressException();
        }

        $this->value = $value;
    }

    public function __toString() {
        return $this->value;
    }
}