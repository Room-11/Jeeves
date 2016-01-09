<?php

namespace Room11\Jeeves\OpenId;

use Amp\Artax\Client as HttpClient;
use Amp\Artax\Request;
use Amp\Artax\FormBody;

class Client
{
    const FKEY_URL = 'https://openid.stackexchange.com/account/login';

    const LOGIN_URL = 'https://openid.stackexchange.com/account/login/submit';

    private $credentials;

    private $httpClient;

    public function __construct(Credentials $credentials, HttpClient $httpClient)
    {
        $this->credentials = $credentials;
        $this->httpClient  = $httpClient;
    }

    public function getFkey(): string
    {
        $promise = $this->httpClient->request(self::FKEY_URL);
        $response = \Amp\wait($promise);

        $dom = new \DOMDocument();
        $dom->loadHTML($response->getBody());

        foreach ($dom->getElementsByTagName('input') as $inputNode) {
            if (!$inputNode->hasAttribute('name') || $inputNode->getAttribute('name') !== 'fkey') {
                continue;
            }

            return $inputNode->getAttribute('value');
        }

        throw new \Exception('fkey node not found on the page');
    }

    public function logIn(string $fkey): bool
    {
        $body = (new FormBody)
            ->addField('email', $this->credentials->getEmailAddress())
            ->addField('password', $this->credentials->getPassword())
            ->addField('fkey', $fkey)
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

    public function getStackOverflowFkey(): string
    {
        $promise = $this->httpClient->request('http://stackoverflow.com/users/login?returnurl=%2f');
        $response = \Amp\wait($promise);

        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getBody());

        foreach ($dom->getElementsByTagName('input') as $inputNode) {
            if (!$inputNode->hasAttribute('name') || $inputNode->getAttribute('name') !== 'fkey') {
                continue;
            }

            return $inputNode->getAttribute('value');
        }

        throw new \Exception('fkey node not found on the page');
    }

    public function logInStackOverflow(string $fkey): bool
    {
        $body = (new FormBody)
            ->addField('email', $this->credentials->getEmailAddress())
            ->addField('password', $this->credentials->getPassword())
            ->addField('fkey', $fkey)
            ->addField('ssrc', '')
            ->addField('oauth_version', '')
            ->addField('oauth_server', '')
            ->addField('openid_username', '')
            ->addField('openid_identifier', '')
        ;

        $request = (new Request)
            ->setUri('http://stackoverflow.com/users/login?returnurl=%2f')
            ->setMethod('POST')
            ->setBody($body)
        ;

        $promise = $this->httpClient->request($request);
        $response = \Amp\wait($promise);

        var_dump('THIS SHOULD BE A POST REQUEST (SEE ABOVE) BUT ARTAX TELLS ME IT IS A GET REQUEST');
        var_dump($response);

        return true;

        //return $this->verifyLogin($response->getBody());
    }

    public function logInStackOverflow_x(string $fkey): bool
    {
        $body = (new FormBody)
            ->addField('email', $this->credentials->getEmailAddress())
            ->addField('password', $this->credentials->getPassword())
            ->addField('fkey', $fkey)
        ;

        $request = (new Request)
            ->setUri('http://stackoverflow.com/users/authenticate')
            ->setMethod('POST')
            ->setBody($body)
        ;

        $promise = $this->httpClient->request($request);
        $response = \Amp\wait($promise);

        return $this->verifyLogin($response->getBody());
    }

    public function getChatStackOverflowFkey(): string
    {
        $promise = $this->httpClient->request('http://chat.stackoverflow.com/rooms/11/php');
        $response = \Amp\wait($promise);

        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getBody());

        foreach ($dom->getElementsByTagName('input') as $inputNode) {
            if (!$inputNode->hasAttribute('name') || $inputNode->getAttribute('name') !== 'fkey') {
                continue;
            }

            return $inputNode->getAttribute('value');
        }

        throw new \Exception('fkey node not found on the page');
    }

    public function getWebSocketUri(string $fkey)
    {
        $body = (new FormBody)
            ->addField('roomid', 100238) // @todo don't hardcode the room id although 11 is the best
            ->addField('fkey', $fkey)
        ;

        $request = (new Request)
            ->setUri('http://chat.stackoverflow.com/ws-auth')
            ->setMethod('POST')
            ->setBody($body)
            ->setHeader('X-Requested-With', 'XMLHttpRequest')
        ;
/*
        $request = (new Request)
            ->setUri('http://chat.stackoverflow.com/rooms/thumbs/11')
            //->setMethod('POST')
            //->setBody($body)
            ->setHeader('X-Requested-With', 'XMLHttpRequest')
        ;
*/

        $promise = $this->httpClient->request($request);
        $response = \Amp\wait($promise);

        var_dump($response);
    }
}
