<?php declare(strict_types=1);

namespace Room11\Jeeves\OpenId;

use Amp\Artax\Client as HttpClient;
use Amp\Artax\Request;
use Amp\Artax\FormBody;
use Room11\Jeeves\Fkey\Retriever as FkeyRetriever;

class OpenIdLogin
{
    const FKEY_URL = 'https://openid.stackexchange.com/account/login';

    const LOGIN_URL = 'https://openid.stackexchange.com/account/login/submit';

    private $credentials;

    private $httpClient;

    private $fkeyRetriever;

    public function __construct(Credentials $credentials, HttpClient $httpClient, FkeyRetriever $fkeyRetriever)
    {
        $this->credentials   = $credentials;
        $this->httpClient    = $httpClient;
        $this->fkeyRetriever = $fkeyRetriever;
    }

    public function logIn()
    {
        $body = (new FormBody)
            ->addField('email', $this->credentials->getEmailAddress())
            ->addField('password', $this->credentials->getPassword())
            ->addField('fkey', $this->fkeyRetriever->get(self::FKEY_URL))
        ;

        $request = (new Request)
            ->setUri(self::LOGIN_URL)
            ->setMethod('POST')
            ->setBody($body)
        ;

        $promise = $this->httpClient->request($request);
        $response = \Amp\wait($promise);

        if (!$this->verifyLogin($response->getBody())) {
            throw new FailedAuthenticationException();
        }
    }

    public function verifyLogin(string $html): bool
    {
        $dom = new \DOMDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors($internalErrors);

        $xpath = new \DOMXPath($dom);

        return !$xpath->evaluate("//*[contains(concat(' ', @class, ' '), ' error ')]")->length;
    }
}
