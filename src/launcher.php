<?php declare(strict_types = 1);

namespace Room11\Jeeves;

use Aerys\Bootstrapper;
use Aerys\Host;
use DaveRandom\AsyncBitlyClient\Client as BitlyClient;
use DaveRandom\AsyncMicrosoftTranslate\Credentials as TranslationAPICredentials;
use PeeHaa\AsyncChatterBot\Credential\CleverBot as CleverBotCredentials;
use PeeHaa\AsyncTwitter\Credentials\Application as TwitterApplicationCredentials;
use Psr\Log\LoggerInterface as Logger;
use Room11\Jeeves\BuiltIn\Commands\Admin as AdminBuiltIn;
use Room11\Jeeves\BuiltIn\Commands\Alias as AliasBuiltIn;
use Room11\Jeeves\BuiltIn\Commands\Ban as BanBuiltIn;
use Room11\Jeeves\BuiltIn\Commands\Command as CommandBuiltIn;
use Room11\Jeeves\BuiltIn\Commands\Plugin as PluginBuiltIn;
use Room11\Jeeves\BuiltIn\Commands\Remove as RemoveBuiltIn;
use Room11\Jeeves\BuiltIn\Commands\RoomPresence;
use Room11\Jeeves\BuiltIn\Commands\Uptime as UptimeBuiltIn;
use Room11\Jeeves\BuiltIn\Commands\Version as VersionBuiltIn;
use Room11\Jeeves\BuiltIn\EventHandlers\Invite;
use Room11\Jeeves\Chat\PresenceManager as ChatRoomPresenceManager;
use Room11\Jeeves\Chat\RoomStatusManager as ChatRoomStatusManager;
use Room11\Jeeves\Chat\WebSocketEventDispatcherFactory;
use Room11\Jeeves\External\GithubIssue\Credentials as GithubIssueCredentials;
use Room11\Jeeves\Log\Level as LogLevel;
use Room11\Jeeves\Log\StdOut as StdOutLogger;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\Storage\CommandAlias as CommandAliasStorage;
use Room11\Jeeves\Storage\File\Admin as FileAdminStorage;
use Room11\Jeeves\Storage\File\Ban as FileBanStorage;
use Room11\Jeeves\Storage\File\CommandAlias as FileCommandAliasStorage;
use Room11\Jeeves\Storage\File\KeyValue as FileKeyValueStorage;
use Room11\Jeeves\Storage\File\Plugin as FilePluginStorage;
use Room11\Jeeves\Storage\File\Room as FileRoomStorage;
use Room11\Jeeves\Storage\KeyValue as KeyValueStorage;
use Room11\Jeeves\Storage\KeyValueFactory as KeyValueStorageFactory;
use Room11\Jeeves\Storage\Plugin as PluginStorage;
use Room11\Jeeves\Storage\Room as RoomStorage;
use Room11\Jeeves\System\BuiltInActionManager;
use Room11\Jeeves\System\Plugin;
use Room11\Jeeves\System\PluginManager;
use Room11\Jeeves\WebAPI\Server as WebAPIServer;
use Room11\OpenId\Credentials;
use Room11\OpenId\EmailAddress as OpenIdEmailAddress;
use Room11\OpenId\Password as OpenIdPassword;
use Room11\StackChat\Auth\CredentialManager;
use Room11\StackChat\Room\Room;
use Symfony\Component\Yaml\Yaml;
use function Amp\onError;
use function Amp\run;

$injector = require __DIR__ . '/setup-di.php';

$builtInCommands = [
    AdminBuiltIn::class,
    AliasBuiltIn::class,
    BanBuiltIn::class,
    CommandBuiltIn::class,
    PluginBuiltIn::class,
    RoomPresence::class,
    UptimeBuiltIn::class,
    VersionBuiltIn::class,
    RemoveBuiltIn::class
];

$builtInEventHandlers = [
    Invite::class,
];

$config = Yaml::parse(file_get_contents(__DIR__ . '/../config/config.yml'));

$injector->alias(AdminStorage::class,    $config['storage']['admin']    ?? FileAdminStorage::class);
$injector->alias(BanStorage::class,      $config['storage']['ban']      ?? FileBanStorage::class);
$injector->alias(CommandAliasStorage::class,      $config['storage']['commandalias']      ?? FileCommandAliasStorage::class);
$injector->alias(KeyValueStorage::class, $config['storage']['keyvalue'] ?? FileKeyValueStorage::class);
$injector->alias(KeyValueStorageFactory::class, ($config['storage']['keyvalue'] ?? FileKeyValueStorage::class) . 'Factory');
$injector->alias(PluginStorage::class,   $config['storage']['plugin']   ?? FilePluginStorage::class);
$injector->alias(RoomStorage::class,     $config['storage']['room']     ?? FileRoomStorage::class);

$injector->define(BitlyClient::class, [':accessToken' => $config['bitly']['accessToken']]);

$injector->define(CleverBotCredentials::class, [
    ':key' => $config['cleverbot']['key'] ?? '',
]);

$injector->define(TwitterApplicationCredentials::class, [
    ':key' => $config['twitter']['consumerKey'] ?? '',
    ':secret' => $config['twitter']['consumerSecret'] ?? '',
]);

$injector->define(TranslationAPICredentials::class, [
    ':clientId'     => $config['ms-translate']['client-id'] ?? '',
    ':clientSecret' => $config['ms-translate']['client-secret'] ?? '',
]);

$injector->define(GithubIssueCredentials::class, [
    ':url'      => $config['github']['url'] ?? '',
    ':username' => $config['github']['username'] ?? '',
    ':password' => $config['github']['password'] ?? '',
    ':token'    => $config['github']['token'] ?? ''
]);

$injector->define(WebSocketEventDispatcherFactory::class, [
   ':devMode' => $config['dev-mode']['enable'] ?? false,
]);

$permanentRooms = array_map(function($room) {
    return new Room(
        (int)$room['id'],
        $room['hostname'] ?? 'chat.stackoverflow.com'
    );
}, $config['rooms']);

$injector->define(ChatRoomStatusManager::class, [':permanentRooms' => $permanentRooms]);

$injector->delegate(Logger::class, function () use ($config) {
    $logLevel = getenv('JEEVES_LOG_LEVEL') ?: ($config['logging']['level'] ?? '');
    $flags = array_map('trim', explode('|', $logLevel));

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

/** @var BuiltInActionManager $builtInActionManager */
$builtInActionManager = $injector->make(BuiltInActionManager::class);

foreach ($builtInCommands as $className) {
    $builtInActionManager->registerCommand($injector->make($className));
}

foreach ($builtInEventHandlers as $className) {
    $builtInActionManager->registerEventHandler($injector->make($className));
}

/** @var PluginManager $pluginManager */
$pluginManager = $injector->make(PluginManager::class);

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

$injector->make(ChatRoomPresenceManager::class)->restoreRooms($permanentRooms);

if ($config['web-api']['enable'] ?? false) {
    $host = new Host;

    $sslEnabled = false;

    if ($config['web-api']['ssl']['enable']) {
        if (!isset($config['web-api']['ssl']['cert-path'])) {
            throw new InvalidConfigurationException('SSL-enabled web API must define a certificate path');
        }

        $sslEnabled = true;
        $sslCert = realpath($config['web-api']['ssl']['cert-path']);

        if (!$sslCert) {
            throw new InvalidConfigurationException('Invalid SSL certificate path');
        }

        $sslKey = null;
        if (isset($config['web-api']['ssl']['key-path']) && !$sslKey = realpath($config['web-api']['ssl']['key-path'])) {
            throw new InvalidConfigurationException('Invalid SSL key path');
        }

        $sslContext = $config['web-api']['ssl']['context'] ?? [];

        $host->encrypt($sslCert, $sslKey, $sslContext);
    }

    $bindAddr = $config['web-api']['bind-addr'] ?? '127.0.0.1';
    $bindPort = (int)($config['web-api']['bind-port'] ?? ($sslEnabled ? 1337 : 1338));

    $host->expose($bindAddr, $bindPort);

    if (isset($config['web-api']['host'])) {
        $host->name($config['web-api']['host']);
    }

    /** @var WebAPIServer $api */
    $api = $injector->make(WebAPIServer::class);

    $host->use($api->getRouter());

    \Amp\wait((new Bootstrapper(function() use($host) { return [$host]; }))
        ->init($injector->make(Logger::class))
        ->start());
}

onError(function (\Throwable $e) {
    fwrite(STDERR, "\nAn exception was not handled:\n\n{$e}\n\n");
});

try {
    run();
} catch (\Throwable $e) {
    fwrite(STDERR, "\nSomething went badly wrong:\n\n{$e}\n\n");
}
