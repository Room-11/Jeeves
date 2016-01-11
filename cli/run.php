<?php declare(strict_types=1);

namespace Room11\Jeeves;

use Amp\Artax\Client as HttpClient;
use Room11\Jeeves\Fkey\Retriever as FkeyRetreiver;
use Room11\Jeeves\OpenId\Client;

use Room11\Jeeves\Chat\Room\Collection as RoomCollection;
use Room11\Jeeves\Chat\Command\Collection as CommandCollection;
use Room11\Jeeves\Chat\Message\Factory as MessageFactory;

use Room11\Jeeves\Chat\Command\Version as VersionCommand;

use Amp\Websocket\Handshake;
use Room11\Jeeves\WebSocket\Handler;

require_once __DIR__ . '/../bootstrap.php';

$httpClient   = new HttpClient();

$fkeyRetriever = new FkeyRetreiver($httpClient);

$openIdClient = new Client($openIdCredentials, $httpClient, $fkeyRetriever);

$openIdClient->logIn();

$roomCollection = new RoomCollection($fkeyRetriever, $httpClient);

$chatKey = $fkeyRetriever->get('http://chat.stackoverflow.com/rooms/100286/php');

$webSocketUrl = $openIdClient->getWebSocketUri();

$commands = (new CommandCollection())
    ->register(new VersionCommand($httpClient, $chatKey))
;

\Amp\run(function () use ($webSocketUrl, $httpClient, $chatKey, $roomCollection, $commands) {
    $handshake = new Handshake($webSocketUrl . '?l=57365782');

    $handshake->setHeader('Origin', "http://chat.stackoverflow.com");

    $webSocket = new Handler(new MessageFactory(), $commands);

    yield \Amp\websocket($webSocket, $handshake);
});
