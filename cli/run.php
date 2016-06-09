#!/usr/bin/env php
<?php declare(strict_types = 1);

namespace Room11\Jeeves;

use Auryn\Injector;
use Room11\Jeeves\External\BitlyClient;
use Room11\Jeeves\Chat\BuiltIn\Admin as AdminBuiltIn;
use Room11\Jeeves\Chat\BuiltIn\Ban as BanBuiltIn;
use Room11\Jeeves\Chat\BuiltIn\Command as CommandBuiltIn;
use Room11\Jeeves\Chat\BuiltIn\Plugin as PluginBuiltIn;
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
use Room11\Jeeves\External\MicrosoftTranslationAPI\Credentials as TranslationAPICredentials;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\Storage\File\Admin as FileAdminStorage;
use Room11\Jeeves\Storage\File\Ban as FileBanStorage;
use Room11\Jeeves\Storage\File\KeyValue as FileKeyValueStorage;
use Room11\Jeeves\Storage\File\Plugin as FilePluginStorage;
use Room11\Jeeves\Storage\KeyValue as KeyValueStorage;
use Room11\Jeeves\Storage\KeyValueFactory as KeyValueStorageFactory;
use Room11\Jeeves\Storage\Plugin as PluginStorage;
use Room11\Jeeves\External\TwitterCredentials;
use Room11\Jeeves\Chat\WebSocket\Handler as WebSocketHandler;
use Room11\Jeeves\Chat\WebSocket\HandlerFactory as WebSocketHandlerFactory;
use Room11\OpenId\Credentials;
use Room11\OpenId\EmailAddress as OpenIdEmailAddress;
use Room11\OpenId\Password as OpenIdPassword;
use Symfony\Component\Yaml\Yaml;
use function Amp\all;
use function Amp\info;
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
$injector->alias(KeyValueStorageFactory::class, ($config['storage']['keyvalue'] ?? FileKeyValueStorage::class) . 'Factory');
$injector->alias(PluginStorage::class,   $config['storage']['plugin']   ?? FilePluginStorage::class);

$injector->define(BitlyClient::class, [':accessToken' => $config['bitly']['accessToken']]);

$injector->define(TwitterCredentials::class, [
    ':consumerKey' => $config['twitter']['consumerKey'],
    ':consumerSecret' => $config['twitter']['consumerSecret'],
    ':accessToken' => $config['twitter']['accessToken'],
    ':accessTokenSecret' => $config['twitter']['accessTokenSecret'],
]);

$injector->define(TranslationAPICredentials::class, [
    ':clientId'     => $config['ms-translate']['client-id'] ?? '',
    ':clientSecret' => $config['ms-translate']['client-secret'] ?? '',
]);

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

    return new $logger($flags, ...array_values($config['logging']['params'] ?? []));
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

/** @var WebSocketHandlerFactory $handlerFactory */
$handlerFactory = $injector->make(WebSocketHandlerFactory::class);

/** @var WebSocketHandler[] $websocketHandlers */
$websocketHandlers = array_map(function($room) use($handlerFactory) {
    return $handlerFactory->build(new ChatRoomIdentifier(
        $room['id'],
        $room['hostname'] ?? 'chat.stackoverflow.com',
        $room['secure'] ?? true
    ));
}, $config['rooms']);

$builtInCommandManager = $injector->make(BuiltInCommandManager::class);
$pluginManager = $injector->make(PluginManager::class);

foreach ($builtInCommands as $command) {
    $builtInCommandManager->register($injector->make($command));
}

foreach ($config['plugins'] ?? [] as $pluginClass) {
    if (!class_exists($pluginClass)) {
        throw new \LogicException("Plugin class {$pluginClass} does not exist");
    } else if (!is_a($pluginClass, Plugin::class, true)) {
        throw new \LogicException("Plugin class {$pluginClass} does not implement " . Plugin::class);
    }

    $injector->define(FileKeyValueStorage::class, [
        ':dataFile' => DATA_BASE_DIR . '/keyvalue.%s.json',
        ':partitionName' => $pluginClass
    ]);

    $pluginManager->registerPlugin($injector->make($pluginClass));
}

/** @var ChatRoomConnector $connector */
$connector = $injector->make(ChatRoomConnector::class);

try {
    run(function () use ($connector, $handlerFactory, $websocketHandlers) {
        $promises = [];

        foreach ($websocketHandlers as $handler) {
            $promises[] = yield from $connector->connect($handler);
        }

        return all($promises);
    });
} catch (\Throwable $e) {
    fwrite(STDERR, "\nSomething went badly wrong:\n\n{$e}\n\n");
}
