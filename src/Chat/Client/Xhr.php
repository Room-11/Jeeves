<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Client;

use Amp\Artax\Client;
use Amp\Artax\FormBody;
use Amp\Artax\Request;

class Xhr
{
    private $httpClient;

    private $fkey;

    private $roomId;

    public function __construct(Client $httpClient, string $fkey, int $roomId)
    {
        $this->httpClient = $httpClient;
        $this->fkey       = $fkey;
        $this->roomId     = $roomId;
    }

    public function request(string $uri): \Generator
    {
        yield $this->httpClient->request($uri);
    }

    public function postMessage(string $text): \Generator
    {
        $body = (new FormBody)
            ->addField('text', $text)
            ->addField('fkey', $this->fkey)
        ;

        $request = (new Request)
            ->setUri('http://chat.stackoverflow.com/chats/' . $this->roomId . '/messages/new')
            ->setMethod('POST')
            ->setBody($body)
        ;

        yield $this->httpClient->request($request);
    }
}
