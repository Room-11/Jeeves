<?php declare(strict_types=1);

namespace Room11\Jeeves\OpenId;

use Amp\Artax\Client as HttpClient;
use Amp\Artax\Request;
use Amp\Artax\FormBody;
use Room11\Jeeves\Fkey\Retriever as FkeyRetriever;

class Client
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

    public function logIn(): bool
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

        return $this->verifyLogin($response->getBody());
    }

    private function verifyLogIn(string $body): bool
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($body);

        $xpath = new \DOMXPath($dom);

        return !$xpath->evaluate("//*[contains(concat(' ', @class, ' '), ' error ')]")->length;
    }

    public function logInStackOverflow(): bool
    {
        $body = (new FormBody)
            ->addField('email', $this->credentials->getEmailAddress())
            ->addField('password', $this->credentials->getPassword())
            ->addField('fkey', $this->fkeyRetriever->get('https://stackoverflow.com/users/login?returnurl=%2f'))
            ->addField('ssrc', '')
            ->addField('oauth_version', '')
            ->addField('oauth_server', '')
            ->addField('openid_username', '')
            ->addField('openid_identifier', '')
        ;

        $request = (new Request)
            ->setUri('https://stackoverflow.com/users/login?returnurl=%2f')
            ->setMethod('POST')
            ->setBody($body)
        ;

        $promise = $this->httpClient->request($request);
        $response = \Amp\wait($promise);

        // @todo check if login succeeded
        return true;
    }

    public function getWebSocketUri()
    {
        $body = (new FormBody)
            ->addField('roomid', 100286) // @todo don't hardcode the room id although 11 is the best
            ->addField('fkey', $this->fkeyRetriever->get('http://chat.stackoverflow.com/rooms/100286/php'))
        ;

        $request = (new Request)
            ->setUri('http://chat.stackoverflow.com/ws-auth')
            ->setMethod('POST')
            ->setBody($body)
        ;

        $promise = $this->httpClient->request($request);
        $response = \Amp\wait($promise);

        return json_decode($response->getBody(), true)['url'];
    }
}
