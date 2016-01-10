<?php declare(strict_types=1);

namespace Room11\Jeeves;

use Amp\Artax\Cookie\FileCookieJar;
use Amp\Artax\Client as HttpClient;
use Room11\Jeeves\OpenId\Client;

use Amp\Websocket\Handshake;
use Room11\Jeeves\WebSocket\Handler;

use Amp\Artax\Request;
use Amp\Artax\FormBody;

require_once __DIR__ . '/../bootstrap.php';

$jarName = __DIR__ . '/../data/cookies' . time() . '.txt';

$cookieJar    = new FileCookieJar($jarName);
$httpClient   = new HttpClient($cookieJar);

$openIdClient = new Client($openIdCredentials, $httpClient);

$fkey = $openIdClient->getFkey();

if (!$openIdClient->logIn($fkey)) {
    throw new \Exception('OpenId log in failed.');
}

$stackOverflowFkey = $openIdClient->getStackOverflowFkey();

if (!$openIdClient->logInStackOverflow($stackOverflowFkey)) {
    throw new \Exception('StackOverflow OpenId log in failed.');
}

$chatKey = $openIdClient->getChatStackOverflowFkey();

$webSocketUrl = $openIdClient->getWebSocketUri($chatKey);

\Amp\run(function () use ($webSocketUrl, $httpClient, $chatKey) {
    $handshake = new Handshake($webSocketUrl . '?l=57365782');

    $handshake->setHeader('Origin', "http://chat.stackoverflow.com");

    $webSocket = new Handler();

    $connection = (yield \Amp\websocket($webSocket, $handshake));

    \Amp\once(function () use ($httpClient, $chatKey) {
        $body = (new FormBody)
            ->addField('text', 'testmessage' . time())
            ->addField('fkey', $chatKey)
        ;

        $request = (new Request)
            ->setUri('http://chat.stackoverflow.com/chats/100286/messages/new')
            ->setMethod('POST')
            ->setBody($body)
        ;

        $promise = $httpClient->request($request);
        $response = yield $promise;
    }, 5000);
});
