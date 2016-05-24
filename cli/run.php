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
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\PluginManager;
use Room11\Jeeves\Chat\Room\Connector as ChatRoomConnector;
use Room11\Jeeves\Chat\Room\CredentialManager;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Log\Level as LogLevel;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Log\StdOut as StdOutLogger;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\Storage\File\Admin as FileAdminStorage;
use Room11\Jeeves\Storage\File\Ban as FileBanStorage;
use Room11\Jeeves\Storage\File\KeyValue as FileKeyValueStorage;
use Room11\Jeeves\Storage\File\Plugin as FilePluginStorage;
use Room11\Jeeves\Storage\KeyValue as KeyValueStorage;
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

$builtInCommands = [
    AdminBuiltIn::class,
    BanBuiltIn::class,
    CommandBuiltIn::class,
    PluginBuiltIn::class,
    VersionBuiltIn::class,
];

$config = Yaml::parse(file_get_contents(__DIR__ . '/../config/config.yml'));

$injector = new Injector();
require_once __DIR__ . '/setup-di.php';

$injector->alias(AdminStorage::class,    $config['storage']['admin']    ?? FileAdminStorage::class);
$injector->alias(BanStorage::class,      $config['storage']['ban']      ?? FileBanStorage::class);
$injector->alias(KeyValueStorage::class, $config['storage']['keyvalue'] ?? FileKeyValueStorage::class);
$injector->alias(PluginStorage::class,   $config['storage']['plugin']   ?? FilePluginStorage::class);

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

    $logger = $config['logging']['handler'] ?? StdOutLogger::class;

    if ($config['logging']['params']) {
        return new $logger($flags, ...array_values($config['logging']['params']));
    }

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

$builtInCommandManager = $injector->make(BuiltInCommandManager::class);
$pluginManager = $injector->make(PluginManager::class);

foreach ($builtInCommands as $command) {
    $builtInCommandManager->register($injector->make($command));
}

call_user_func(function() use($config, $pluginManager, $injector) {
    $pluginClass = null; // fixme: this is a horrible horrible hack

    $injector->delegate(FileKeyValueStorage::class, function() use(&$pluginClass) {
        return new FileKeyValueStorage(DATA_BASE_DIR . "/keyvalue.%s.json", $pluginClass);
    });

    foreach ($config['plugins'] ?? [] as $pluginClass) {
        if (!is_a($pluginClass, Plugin::class, true)) {
            throw new \LogicException("Plugin class {$pluginClass} does not implement " . Plugin::class);
        }

        $pluginManager->registerPlugin($injector->make($pluginClass));
    }
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
