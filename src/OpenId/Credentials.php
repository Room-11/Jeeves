<?php

namespace Room11\Jeeves\OpenId;

class Credentials
{
    private $emailAddress;

    private $password;

    public function __construct($emailAddress, $password)
    {
        $this->emailAddress = $emailAddress;
        $this->password     = $password;
    }

    public function getEmailAddress()
    {
        return $this->emailAddress;
    }

    public function getPassword()
    {
        return $this->password;
    }
}
