#!/usr/bin/env php
<?php declare(strict_types = 1);

namespace Room11\Jeeves;

use Amp\Artax\HttpClient;
use Amp\Artax\Client as ArtaxClient;
use Amp\Websocket\Handshake;
use Auryn\Injector;
use Room11\Jeeves\Bitly\Client as BitlyClient;
use Room11\Jeeves\Chat\BuiltIn\Admin as AdminBuiltIn;
use Room11\Jeeves\Chat\BuiltIn\Ban as BanBuiltIn;
use Room11\Jeeves\Chat\BuiltIn\Version as VersionBuiltIn;
use Room11\Jeeves\Chat\BuiltInCommandManager;
use Room11\Jeeves\Chat\Client\ChatClient;
//use Room11\Jeeves\Chat\Plugin\CodeFormat as CodeFormatPlugin;
use Room11\Jeeves\Chat\PluginManager;
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
use Room11\Jeeves\OpenId\Client as OpenIdClient;
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
    $flags = array_map("trim", explode("|", $config["logging"]["level"] ?? ""));

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

$injector->alias(HttpClient::class, ArtaxClient::class);
$injector->alias(AdminStorage::class, $config["storage"]["admin"]);
$injector->alias(BanStorage::class, $config["storage"]["ban"]);
$injector->define(AdminStorage::class, [":dataFile" => __DIR__ . "/../data/admins.json"]);
$injector->define(BanStorage::class, [":dataFile" => __DIR__ . "/../data/bans.json"]);
$injector->share(AdminStorage::class);
$injector->share(BanStorage::class);

$injector->define(TwitterCredentials::class, [
    ":consumerKey" => $config["twitter"]["consumerKey"],
    ":consumerSecret" => $config["twitter"]["consumerSecret"],
    ":accessToken" => $config["twitter"]["accessToken"],
    ":accessTokenSecret" => $config["twitter"]["accessTokenSecret"],
]);
$injector->define(BitlyClient::class, [
    ":accessToken" => $config["bitly"]["accessToken"],
]);

$injector->delegate(PluginManager::class, function () use ($injector) {
    $pluginManager = new PluginManager($injector->make(AdminStorage::class), $injector->make(BanStorage::class));

    $plugins = [
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
        $pluginManager->register($injector->make($plugin));
    }

    return $pluginManager;
});

$injector->delegate(BuiltInCommandManager::class, function () use ($injector) {
    $builtInCommandManager = new BuiltInCommandManager($injector->make(BanStorage::class));

    $commands = [AdminBuiltIn::class, BanBuiltIn::class, VersionBuiltIn::class];

    foreach ($commands as $command) {
        $builtInCommandManager->register($injector->make($command));
    }

    return $builtInCommandManager;
});

$injector->share(OpenIdClient::class);
$injector->share(Logger::class);
$injector->share(BitlyClient::class);
$injector->share(HttpClient::class);
$injector->share(ChatClient::class);
$injector->share(new EmailAddress($config["username"]));
$injector->share(new Password($config["password"]));

$openIdClient = $injector->make(OpenIdClient::class);
$openIdClient->logIn();

$room = $injector->make(Room::class);
$handshake = new Handshake($openIdClient->getWebSocketUri($room));
$handshake->setHeader("Origin", sprintf(
    "%s://%s",
    $room->getHost()->isSecure() ? "wss" : "ws",
    $room->getHost()->getHostname()
));

$webSocket = $injector->make(Handler::class);

\Amp\run(function () use ($webSocket, $handshake) {
    yield \Amp\websocket($webSocket, $handshake);
});
