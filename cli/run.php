#!/usr/bin/env php
<?php declare(strict_types = 1);

namespace Room11\Jeeves;

use Amp\Artax\Client as HttpClient;
use Amp\Websocket\Handshake;
use Auryn\Injector;
use Room11\Jeeves\Chat\Message\Factory as MessageFactory;
use Room11\Jeeves\Chat\BuiltIn\AdminManager;
use Room11\Jeeves\Chat\BuiltIn\BanManager;
use Room11\Jeeves\Chat\BuiltIn\VersionManager;
//use Room11\Jeeves\Chat\Plugin\CodeFormat as CodeFormatPlugin;
use Room11\Jeeves\Chat\PluginManager as PluginCollection;
use Room11\Jeeves\Chat\Plugin\Canon as CanonPlugin;
use Room11\Jeeves\Chat\Plugin\Docs as DocsPlugin;
use Room11\Jeeves\Chat\Plugin\EvalCode as EvalPlugin;
use Room11\Jeeves\Chat\Plugin\Imdb as ImdbPlugin;
use Room11\Jeeves\Chat\Plugin\Lick as LickPlugin;
use Room11\Jeeves\Chat\Plugin\Man as ManPlugin;
use Room11\Jeeves\Chat\Plugin\Packagist as PackagistPlugin;
use Room11\Jeeves\Chat\Plugin\Regex as RegexPlugin;
use Room11\Jeeves\Chat\Plugin\RFC as RfcPlugin;
use Room11\Jeeves\Chat\Plugin\SwordFight as SwordFightPlugin;
use Room11\Jeeves\Chat\Plugin\Tweet as TweetPlugin;
use Room11\Jeeves\Chat\Plugin\Urban as UrbanPlugin;
use Room11\Jeeves\Chat\Plugin\Wikipedia as WikipediaPlugin;
use Room11\Jeeves\Chat\Plugin\Xkcd as XkcdPlugin;
use Room11\Jeeves\Chat\Plugin\Mdn as MdnPlugin;
use Room11\Jeeves\Chat\Plugin\Chuck as ChuckPlugin;
use Room11\Jeeves\Chat\Plugin\Rebecca as RebeccaPlugin;
use Room11\Jeeves\Chat\Plugin\Wotd as WotdPlugin;
use Room11\Jeeves\Chat\Plugin\Google as GooglePlugin;
use Room11\Jeeves\Chat\Room\Host;
use Room11\Jeeves\Chat\Room\Room;
use Room11\Jeeves\Fkey\FKey;
use Room11\Jeeves\Fkey\Retriever;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Log\StdOut;
use Room11\Jeeves\OpenId\Client;
use Room11\Jeeves\OpenId\EmailAddress;
use Room11\Jeeves\OpenId\Password;
use Room11\Jeeves\Twitter\Credentials as TwitterCredentials;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\WebSocket\Handler;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../version.php";

$config = Yaml::parse(file_get_contents(__DIR__ . "/../config/config.yml"));

$injector = new Injector();

$injector->define(Host::class, [
    ":hostname" => $config["room"]["hostname"] ?? "chat.stackoverflow.com",
    ":secure" => $config["room"]["secure"] ?? true,
]);

$injector->define(Room::class, [
    ":id" => $config["room"]["id"],
]);

$injector->delegate(Logger::class, function () use ($config) {
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
});

$injector->delegate(FKey::class, function (Retriever $retriever, Room $room) {
    $uri = sprintf(
        "%s://%s/rooms/%d",
        $room->getHost()->isSecure() ? "https" : "http",
        $room->getHost()->getHostname(),
        $room->getId()
    );

    $key = $retriever->get($uri);

    return $key;
});

$injector->alias(AdminStorage::class, $config["storage"]["admin"]);
$injector->alias(BanStorage::class, $config["storage"]["ban"]);
$injector->define(AdminStorage::class, [":dataFile" => __DIR__ . "/../data/admins.json"]);
$injector->define(BanStorage::class, [":dataFile" => __DIR__ . "/../data/bans.json"]);
$injector->define(TwitterCredentials::class, [
    ":consumerKey" => $config["twitter"]["consumerKey"],
    ":consumerSecret" => $config["twitter"]["consumerSecret"],
    ":accessToken" => $config["twitter"]["accessToken"],
    ":accessTokenSecret" => $config["twitter"]["accessTokenSecret"],
]);
$injector->define(GooglePlugin::class, [
    ":bitlyAccessToken" => $config["bitly"]["accessToken"],
]);
$injector->delegate(PluginCollection::class, function () use ($injector) {
    $collection = new PluginCollection($injector->make(MessageFactory::class), $injector->make(BanStorage::class));

    $plugins = [
        AdminManager::class,
        BanManager::class,
        VersionManager::class,
        UrbanPlugin::class,
        WikipediaPlugin::class,
        SwordFightPlugin::class,
        DocsPlugin::class,
        ImdbPlugin::class,
        PackagistPlugin::class,
        RfcPlugin::class,
        //CodeFormatPlugin::class,
        EvalPlugin::class,
        CanonPlugin::class,
        ManPlugin::class,
        RegexPlugin::class,
        LickPlugin::class,
        XkcdPlugin::class,
        TweetPlugin::class,
        MdnPlugin::class,
        ChuckPlugin::class,
        RebeccaPlugin::class,
        WotdPlugin::class,
        GooglePlugin::class,
    ];

    foreach ($plugins as $plugin) {
        $collection->register($injector->make($plugin));
    }

    return $collection;
});

$injector->share(Client::class);
$injector->share(Logger::class);
$injector->share(HttpClient::class);
$injector->share(new EmailAddress($config["username"]));
$injector->share(new Password($config["password"]));

$openIdClient = $injector->make(Client::class);
$openIdClient->logIn();

$room = $injector->make(Room::class);
$handshake = new Handshake($openIdClient->getWebSocketUri($room) . "?l=57365782");
$handshake->setHeader("Origin", sprintf(
    "%s://%s",
    $room->getHost()->isSecure() ? "wss" : "ws",
    $room->getHost()->getHostname()
));

$webSocket = $injector->make(Handler::class);

\Amp\run(function () use ($webSocket, $handshake) {
    yield \Amp\websocket($webSocket, $handshake);
});
