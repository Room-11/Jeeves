<?php

namespace Room11\Jeeves;

use Amp\Artax\Cookie\FileCookieJar;
use Amp\Artax\Client as HttpClient;
use Room11\Jeeves\OpenId\Client;

//use Amp\Websocket\Handshake;
//use Room11\Jeeves\WebSocket\Handler;

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

//$httpClient->setOption(HttpClient::OP_VERBOSITY, HttpClient::VERBOSE_ALL);

if (!$openIdClient->logInStackOverflow($stackOverflowFkey)) {
    throw new \Exception('StackOverflow OpenId log in failed.');
}

$chatKey = $openIdClient->getChatStackOverflowFkey();

//$httpClient->setOption(HttpClient::OP_VERBOSITY, HttpClient::VERBOSE_SEND);

$webSocketurl = $openIdClient->getWebSocketUri($chatKey);

var_dump($webSocketurl);

/*
\Amp\run(function () {
    $handshake = new Handshake('wss://chat.sockets.stackexchange.com:443/events/11/474d5f162b1c49dc93cca2b475988e13?l=57332223');
    $webSocket = new Handler();

    $connection = (yield \Amp\websocket($webSocket, $handshake));
});
*/
