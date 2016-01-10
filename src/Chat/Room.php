<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat;

use Room11\Jeeves\Fkey\Retriever as FkeyRetriever;
use Amp\Artax\Client as HttpClient;
use Amp\Artax\Request;
use Amp\Artax\FormBody;

class Room
{
    private $fkeyRetriever;

    private $httpClient;

    private $roomId;

    public function __construct(FkeyRetriever $fkeyRetriever, HttpClient $httpClient, int $roomId)
    {
        $this->fkeyRetriever = $fkeyRetriever;
        $this->httpClient    = $httpClient;
        $this->roomId        = $roomId;
    }

    public function join(): Room
    {
        $url = $this->getWebSocketUrl();

        var_dump('joined room ' . $this->roomId);
        var_dump($url);

        return $this;
    }

    private function getWebSocketUrl()
    {
        $body = (new FormBody)
            ->addField('roomid', $this->roomId)
            ->addField('fkey', $this->fkeyRetriever->get('http://chat.stackoverflow.com/rooms/' . $this->roomId . '/php'))
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
