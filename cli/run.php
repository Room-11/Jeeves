<?php

namespace Room11\Jeeves;

use Amp\Artax\Cookie\FileCookieJar;
use Amp\Artax\Client as HttpClient;
use Room11\Jeeves\OpenId\Client;

//use Amp\Websocket\Handshake;
//use Room11\Jeeves\WebSocket\Handler;

require_once __DIR__ . '/../bootstrap.php';

$cookieJar    = new FileCookieJar(__DIR__ . '/../data/cookies' . time() . '.txt');
$httpClient   = new HttpClient($cookieJar);

$openIdClient = new Client($openIdCredentials, $httpClient);

$fkey = $openIdClient->getFkey();

if (!$openIdClient->logIn($fkey)) {
    throw new \Exception('OpenId log in failed.');
}

$openIdClient->getWebSocketUri($fkey);

/*
\Amp\run(function () {
    $handshake = new Handshake('wss://chat.sockets.stackexchange.com:443/events/11/474d5f162b1c49dc93cca2b475988e13?l=57332223');
    $webSocket = new Handler();

    $connection = (yield \Amp\websocket($webSocket, $handshake));
});
*/
