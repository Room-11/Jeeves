<?php declare(strict_types=1);

namespace Room11\Jeeves\External\GithubIssue;

use Room11\Jeeves\Exception;

class MissingCredentialException extends Exception {}

class Credentials
{
    private $username;
    private $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function get(string $credential): string
    {
        if (!property_exists($this, $credential)) {
            throw new MissingCredentialException(
                'Credential [' . $credential . '] does not exist'
            );
        }

        return $this->$credential;
    }

    public function exists(string $credential): bool
    {
        if (!property_exists($this, $credential)) {    
            return false;
        }

        return true;
    }
}
