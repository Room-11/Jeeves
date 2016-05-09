<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\Room;

use Room11\OpenId\Credentials;

class CredentialManager
{
    private $defaultCredentials;
    private $domainCredentials = [];

    public function setCredentialsForDomain(string $domain, Credentials $credentials)
    {
        $this->domainCredentials[$domain] = $credentials;
    }

    public function setDefaultCredentials(Credentials $credentials)
    {
        $this->defaultCredentials = $credentials;
    }

    public function getCredentialsForDomain(string $domain): Credentials
    {
        return $this->domainCredentials[$domain] ?? $this->defaultCredentials;
    }
}
