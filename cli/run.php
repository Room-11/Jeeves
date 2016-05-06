#!/usr/bin/env php
<?php declare(strict_types = 1);

namespace Room11\Jeeves;

use Auryn\Injector;
use Room11\Jeeves\Bitly\Client as BitlyClient;
use Room11\Jeeves\Chat\BuiltIn\Admin as AdminBuiltIn;
use Room11\Jeeves\Chat\BuiltIn\Ban as BanBuiltIn;
use Room11\Jeeves\Chat\BuiltIn\Version as VersionBuiltIn;
use Room11\Jeeves\Chat\BuiltInCommandManager;
use Room11\Jeeves\Chat\Plugin\Canon as CanonPlugin;
use Room11\Jeeves\Chat\Plugin\Chuck as ChuckPlugin;
//use Room11\Jeeves\Chat\Plugin\CodeFormat as CodeFormatPlugin;
use Room11\Jeeves\Chat\Plugin\Docs as DocsPlugin;
use Room11\Jeeves\Chat\Plugin\EvalCode as EvalPlugin;
use Room11\Jeeves\Chat\Plugin\Google as GooglePlugin;
use Room11\Jeeves\Chat\Plugin\HttpClient as HttpClientPlugin;
use Room11\Jeeves\Chat\Plugin\Imdb as ImdbPlugin;
use Room11\Jeeves\Chat\Plugin\Lick as LickPlugin;
use Room11\Jeeves\Chat\Plugin\Man as ManPlugin;
use Room11\Jeeves\Chat\Plugin\Mdn as MdnPlugin;
use Room11\Jeeves\Chat\Plugin\Packagist as PackagistPlugin;
use Room11\Jeeves\Chat\Plugin\Rebecca as RebeccaPlugin;
use Room11\Jeeves\Chat\Plugin\Regex as RegexPlugin;
use Room11\Jeeves\Chat\Plugin\RFC as RfcPlugin;
use Room11\Jeeves\Chat\Plugin\SwordFight as SwordFightPlugin;
use Room11\Jeeves\Chat\Plugin\Tweet as TweetPlugin;
use Room11\Jeeves\Chat\Plugin\Urban as UrbanPlugin;
use Room11\Jeeves\Chat\Plugin\Wikipedia as WikipediaPlugin;
use Room11\Jeeves\Chat\Plugin\Wotd as WotdPlugin;
use Room11\Jeeves\Chat\Plugin\Xkcd as XkcdPlugin;
use Room11\Jeeves\Chat\PluginManager;
use Room11\Jeeves\Chat\Room\Connector as ChatRoomConnector;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Log\Level as LogLevel;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Log\StdOut as StdOutLogger;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\Twitter\Credentials as TwitterCredentials;
use Room11\Jeeves\WebSocket\Collection as WebSocketCollection;
use Room11\OpenId\EmailAddress as OpenIdEmailAddress;
use Room11\OpenId\Password as OpenIdPassword;
use Symfony\Component\Yaml\Yaml;
use function Amp\resolve;
use function Amp\run;
use function Amp\wait;
use function Amp\websocket;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../version.php';

$config = Yaml::parse(file_get_contents(__DIR__ . '/../config/config.yml'));

$injector = new Injector();
require_once __DIR__ . '/setup-di.php';

$injector->alias(AdminStorage::class, $config['storage']['admin']);
$injector->alias(BanStorage::class, $config['storage']['ban']);

$injector->define(BitlyClient::class, [':accessToken' => $config['bitly']['accessToken']]);
$injector->define(OpenIdEmailAddress::class, [':value' => $config['username']]);
$injector->define(OpenIdPassword::class, [':value' => $config['password']]);
$injector->define(TwitterCredentials::class, [
    ':consumerKey' => $config['twitter']['consumerKey'],
    ':consumerSecret' => $config['twitter']['consumerSecret'],
    ':accessToken' => $config['twitter']['accessToken'],
    ':accessTokenSecret' => $config['twitter']['accessTokenSecret'],
]);

$primaryRoomIdentifier = new ChatRoomIdentifier(
    $config['room']['id'],
    $config['room']['hostname'] ?? 'chat.stackoverflow.com',
    $config['room']['secure'] ?? true
);

$injector->delegate(Logger::class, function () use ($config) {
    $flags = array_map('trim', explode('|', $config['logging']['level'] ?? ''));

    if (empty($flags[0])) {
        $flags = LogLevel::ALL;
    } else {
        $flags = array_reduce($flags, function ($carry, $flag) {
            return $carry | constant(LogLevel::class . "::{$flag}");
        }, 0);
    }

    $logger = $config['handler'] ?? StdOutLogger::class;
    return new $logger($flags);
});

$injector->delegate(BuiltInCommandManager::class, function () use ($injector) {
    $builtInCommandManager = new BuiltInCommandManager($injector->make(BanStorage::class), $injector->make(Logger::class));

    $commands = [AdminBuiltIn::class, BanBuiltIn::class, VersionBuiltIn::class];

    foreach ($commands as $command) {
        $builtInCommandManager->register($injector->make($command));
    }

    return $builtInCommandManager;
});

$injector->delegate(PluginManager::class, function () use ($injector) {
    $pluginManager = new PluginManager($injector->make(BanStorage::class), $injector->make(Logger::class));

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
        HttpClientPlugin::class,
    ];

    foreach ($plugins as $plugin) {
        $pluginManager->register($injector->make($plugin));
    }

    return $pluginManager;
});

try {
    run(function () use ($injector, $primaryRoomIdentifier) {
        /** @var ChatRoomIdentifier[] $identifiers */
        /** @var ChatRoomConnector $connector */
        /** @var WebSocketCollection $sockets */

        $identifiers = [$primaryRoomIdentifier, new ChatRoomIdentifier(100286, 'chat.stackoverflow.com', true)];

        $connector = $injector->make(ChatRoomConnector::class);
        $sockets = $injector->make(WebSocketCollection::class);

        foreach ($identifiers as $identifier) {
            yield from $connector->connect($identifier);
        }

        yield from $sockets->yieldAll();
    });
} catch (\Throwable $e) {
    fwrite(STDERR, "\nSomething went badly wrong:\n\n{$e}\n\n");
}
