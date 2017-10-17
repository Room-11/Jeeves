<?php declare(strict_types=1);

namespace Room11\Jeeves\External\GithubIssue;

use Room11\Jeeves\Exception;

class MissingCredentialException extends Exception {}

class Credentials
{
    private $username;
    private $password;
    private $token;
    private $url;

    public function __construct(
        string $url,
        string $username, 
        string $password, 
        string $token
    ) {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->token = $token;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
