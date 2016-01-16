<?php declare(strict_types=1);

namespace Room11\Jeeves;

use Amp\Artax\Client as HttpClient;
use Room11\Jeeves\Fkey\Retriever as FkeyRetriever;
use Room11\Jeeves\OpenId\Client;

use Room11\Jeeves\Chat\Room\Collection as RoomCollection;
use Room11\Jeeves\Chat\Plugin\Collection as CommandCollection;
use Room11\Jeeves\Chat\Message\Factory as MessageFactory;

use Room11\Jeeves\Chat\Plugin\Version as VersionCommand;
use Room11\Jeeves\Chat\Plugin\Urban as UrbanCommand;
use Room11\Jeeves\Chat\Plugin\Wikipedia as WikipediaCommand;

use Amp\Websocket\Handshake;
use Room11\Jeeves\WebSocket\Handler;

require_once __DIR__ . '/../bootstrap.php';

$httpClient   = new HttpClient();

$fkeyRetriever = new FkeyRetriever($httpClient);

$openIdClient = new Client($openIdCredentials, $httpClient, $fkeyRetriever);

$openIdClient->logIn();

$chatKey = $fkeyRetriever->get('http://chat.stackoverflow.com/rooms/' . $roomId . '/php');

$webSocketUrl = $openIdClient->getWebSocketUri($roomId);

$commands = (new CommandCollection())
    ->register(new VersionCommand($httpClient, $chatKey))
    ->register(new UrbanCommand($httpClient, $chatKey))
    ->register(new WikipediaCommand($httpClient, $chatKey))
;

\Amp\run(function () use ($webSocketUrl, $commands, $logger) {
    $handshake = new Handshake($webSocketUrl . '?l=57365782');

    $handshake->setHeader('Origin', "http://chat.stackoverflow.com");

    $webSocket = new Handler(new MessageFactory(), $commands, $logger);

    yield \Amp\websocket($webSocket, $handshake);
});
