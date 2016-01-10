<?php declare(strict_types=1);

namespace Room11\Jeeves\OpenId;

class Credentials
{
    private $emailAddress;

    private $password;

    public function __construct(string $emailAddress, string $password)
    {
        $this->validateEmailAddress($emailAddress);

        $this->emailAddress = $emailAddress;
        $this->password     = $password;
    }

    private function validateEmailAddress(string $emailAddress)
    {
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailAddressException();
        }
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
