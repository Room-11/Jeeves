#!/usr/bin/env php
<?php declare(strict_types = 1);

namespace Room11\Jeeves;

use Auryn\Injector;
use Room11\Jeeves\Bitly\Client as BitlyClient;
use Room11\Jeeves\Chat\BuiltIn\Admin as AdminBuiltIn;
use Room11\Jeeves\Chat\BuiltIn\Ban as BanBuiltIn;
use Room11\Jeeves\Chat\BuiltIn\Command as CommandBuiltIn;
use Room11\Jeeves\Chat\BuiltIn\Plugin AS PluginBuiltIn;
use Room11\Jeeves\Chat\BuiltIn\Version as VersionBuiltIn;
use Room11\Jeeves\Chat\BuiltInCommandManager;
use Room11\Jeeves\Chat\Event\Filter\Builder as EventFilterBuilder;
use Room11\Jeeves\Chat\PluginManager;
use Room11\Jeeves\Chat\Room\Connector as ChatRoomConnector;
use Room11\Jeeves\Chat\Room\CredentialManager;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Log\Level as LogLevel;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Log\StdOut as StdOutLogger;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\Storage\Plugin as PluginStorage;
use Room11\Jeeves\Twitter\Credentials as TwitterCredentials;
use Room11\Jeeves\WebSocket\Collection as WebSocketCollection;
use Room11\OpenId\Credentials;
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
$injector->alias(PluginStorage::class, $config['storage']['plugin']);

$injector->define(BitlyClient::class, [':accessToken' => $config['bitly']['accessToken']]);

$injector->define(TwitterCredentials::class, [
    ':consumerKey' => $config['twitter']['consumerKey'],
    ':consumerSecret' => $config['twitter']['consumerSecret'],
    ':accessToken' => $config['twitter']['accessToken'],
    ':accessTokenSecret' => $config['twitter']['accessTokenSecret'],
]);

$roomIdentifiers = array_map(function($room) {
    return new ChatRoomIdentifier(
        $room['id'],
        $room['hostname'] ?? 'chat.stackoverflow.com',
        $room['secure'] ?? true
    );
}, $config['rooms']);

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

$injector->delegate(CredentialManager::class, function () use ($config) {
    $manager = new CredentialManager;

    $haveDefault = false;

    foreach ($config['openids'] ?? [] as $domain => $details) {
        if (!isset($details['username'], $details['password'])) {
            throw new InvalidConfigurationException(
                "OpenID domain '{$domain}' does not define username and password"
            );
        }

        $details = new Credentials(
            new OpenIdEmailAddress($details['username']),
            new OpenIdPassword($details['password'])
        );

        if ($domain === 'default') {
            $haveDefault = true;
            $manager->setDefaultCredentials($details);
        } else {
            $manager->setCredentialsForDomain($domain, $details);
        }
    }

    if (!$haveDefault) {
        throw new InvalidConfigurationException('Default OpenID credentials not defined');
    }

    return $manager;
});

$injector->delegate(BuiltInCommandManager::class, function () use ($injector) {
    $builtInCommandManager = new BuiltInCommandManager($injector->make(BanStorage::class), $injector->make(Logger::class));

    $commands = [AdminBuiltIn::class, BanBuiltIn::class, CommandBuiltIn::class, PluginBuiltIn::class, VersionBuiltIn::class];

    foreach ($commands as $command) {
        $builtInCommandManager->register($injector->make($command));
    }

    return $builtInCommandManager;
});

$injector->delegate(PluginManager::class, function () use ($injector, $config) {
    $pluginManager = new PluginManager(
        $injector->make(BanStorage::class),
        $injector->make(PluginStorage::class),
        $injector->make(Logger::class),
        $injector->make(EventFilterBuilder::class)
    );

    foreach ($config['plugins'] ?? [] as $plugin) {
        $pluginManager->registerPlugin($injector->make($plugin));
    }

    return $pluginManager;
});

try {
    run(function () use ($injector, $roomIdentifiers) {
        /** @var ChatRoomConnector $connector */
        /** @var WebSocketCollection $sockets */

        $connector = $injector->make(ChatRoomConnector::class);
        $sockets = $injector->make(WebSocketCollection::class);

        foreach ($roomIdentifiers as $identifier) {
            yield from $connector->connect($identifier);
        }

        yield from $sockets->yieldAll();
    });
} catch (\Throwable $e) {
    fwrite(STDERR, "\nSomething went badly wrong:\n\n{$e}\n\n");
}
