<?php declare(strict_types = 1);

namespace Room11\Jeeves\OpenId;

class Credentials {
    private $emailAddress;
    private $password;

    public function __construct(EmailAddress $emailAddress, Password $password) {
        $this->emailAddress = $emailAddress;
        $this->password     = $password;
    }

    public function getEmailAddress(): EmailAddress {
        return $this->emailAddress;
    }

    public function getPassword(): Password {
        return $this->password;
    }
}
