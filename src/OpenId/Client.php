<?php declare(strict_types=1);

namespace Room11\Jeeves\OpenId;

use Amp\Artax\Client as HttpClient;
use Amp\Artax\Request;
use Amp\Artax\FormBody;
use Room11\Jeeves\Fkey\Retriever as FkeyRetriever;

class Client
{
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
        (new OpenIdLogin($this->credentials, $this->httpClient, $this->fkeyRetriever))->logIn();
        (new StackOverflowLogin($this->credentials, $this->httpClient, $this->fkeyRetriever))->logIn();
    }

    public function getWebSocketUri(int $roomId): string
    {
        $body = (new FormBody)
            ->addField('roomid', $roomId) // @todo don't hardcode the room id although 11 is the best
            ->addField('fkey', $this->fkeyRetriever->get('http://chat.stackoverflow.com/rooms/' . $roomId . '/php'))
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
