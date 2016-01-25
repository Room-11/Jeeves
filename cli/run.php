<?php declare(strict_types = 1);

namespace Room11\Jeeves;

use Amp\Artax\Client as HttpClient;
use Amp\Websocket\Handshake;
use Room11\Jeeves\Chat\Client\ChatClient as ChatClient;
use Room11\Jeeves\Chat\Command\Factory as CommandFactory;
use Room11\Jeeves\Chat\Message\Factory as MessageFactory;
use Room11\Jeeves\Chat\Plugin\Collection as PluginCollection;
use Room11\Jeeves\Chat\Plugin\Docs as DocsPlugin;
use Room11\Jeeves\Chat\Plugin\Imdb as ImdbPlugin;
use Room11\Jeeves\Chat\Plugin\Packagist as PackagistPlugin;
use Room11\Jeeves\Chat\Plugin\SwordFight as SwordFightPlugin;
use Room11\Jeeves\Chat\Plugin\Urban as UrbanPlugin;
use Room11\Jeeves\Chat\Plugin\Version as VersionPlugin;
use Room11\Jeeves\Chat\Plugin\Wikipedia as WikipediaPlugin;
use Room11\Jeeves\Chat\Room\Host;
use Room11\Jeeves\Chat\Room\Room;
use Room11\Jeeves\Fkey\Retriever as FkeyRetriever;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\StdOut;
use Room11\Jeeves\OpenId\Client;
use Room11\Jeeves\OpenId\Credentials;
use Room11\Jeeves\WebSocket\Handler;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../version.php";

function loggerFromConfig(array $config) {
    $flags = array_map("trim", explode("|", $config["level"] ?? ""));

    if (empty($flags[0])) {
        $flags = Level::ALL;
    } else {
        $flags = array_reduce($flags, function ($carry, $flag) {
            return $carry | constant(Level::class . "::$flag");
        }, 0);
    }

    $logger = $config["handler"] ?? StdOut::class;
    return new $logger($flags);
}

$config = Yaml::parse(file_get_contents(__DIR__ . "/../config/config.yml"));

$logger = loggerFromConfig($config["logging"] ?? []);
$openIdCredentials = new Credentials($config["username"], $config["password"]);

$httpClient = new HttpClient();
$fkeyRetriever = new FkeyRetriever($httpClient);
$openIdClient = new Client($openIdCredentials, $httpClient, $fkeyRetriever);

$openIdClient->logIn();

$host = new Host($config["room"]["hostname"] ?? "chat.stackoverflow.com", $config["room"]["secure"] ?? true);
$room = new Room($config["room"]["id"], $host);

$uri = sprintf(
    "%s://%s/rooms/%d",
    $room->getHost()->isSecure() ? "https" : "http",
    $room->getHost()->getHostname(),
    $room->getId()
);

$chatClient = new ChatClient(
    $httpClient,
    $fkeyRetriever->get($uri),
    $room
);

$commands = (new PluginCollection(new CommandFactory()))
    ->register(new VersionPlugin($chatClient))
    ->register(new UrbanPlugin($chatClient))
    ->register(new WikipediaPlugin($chatClient))
    ->register(new SwordFightPlugin($chatClient))
    ->register(new DocsPlugin($chatClient))
    ->register(new ImdbPlugin($chatClient))
    ->register(new PackagistPlugin($chatClient));

$webSocketUrl = $openIdClient->getWebSocketUri($room);

\Amp\run(function () use ($webSocketUrl, $commands, $logger, $room) {
    $handshake = new Handshake($webSocketUrl . '?l=57365782');

    $origin = sprintf(
        "%s://%s",
        $room->getHost()->isSecure() ? "wss" : "ws",
        $room->getHost()->getHostname()
    );

    $handshake->setHeader('Origin', $origin);

    $webSocket = new Handler(new MessageFactory(), $commands, $logger);

    yield \Amp\websocket($webSocket, $handshake);
});
