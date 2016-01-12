<?php

namespace Room11\Jeeves;

use Amp\Artax\Client as HttpClient;
use Amp\Artax\Request;
use Amp\Artax\FormBody;

class Test
{
    private $httpClient;

    private $chatKey;

    public function __construct(HttpClient $httpClient, string $chatKey)
    {
        $this->httpClient = $httpClient;
        $this->chatKey    = $chatKey;
    }

    public function foo(): \Generator
    {
        $body = (new FormBody)
            ->addField('text', 'from ondata event testmessage' . time())
            ->addField('fkey', $this->chatKey)
        ;

        $request = (new Request)
            ->setUri('http://chat.stackoverflow.com/chats/1/messages/new')
            ->setMethod('POST')
            ->setBody($body)
        ;

        $promise = $this->httpClient->request($request);

        yield $promise;

        var_dump('bar?');
    }
}
