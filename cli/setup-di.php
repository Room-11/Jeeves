<?php

namespace Room11\Jeeves;

use Amp\Artax\Client as ArtaxClient;
use Amp\Artax\HttpClient;
use Auryn\Injector;
use Room11\Jeeves\Bitly\Client as BitlyClient;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Event\Filter\Builder as EventFilterBuilder;
use Room11\Jeeves\Chat\PluginManager;
use Room11\Jeeves\Chat\Room\Authenticator as ChatRoomConnector;
use Room11\Jeeves\Chat\Room\Collection as ChatRoomCollection;
use Room11\Jeeves\Chat\Room\CredentialManager;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\Storage\File\Admin as FileAdminStorage;
use Room11\Jeeves\Storage\File\Ban as FileBanStorage;
use Room11\Jeeves\WebSocket\Collection as WebSocketCollection;
use Room11\OpenId\Authenticator as OpenIdAuthenticator;
use Room11\OpenId\StackExchangeAuthenticator;
use Room11\OpenId\UriFactory;

/** @var Injector $injector */
$injector->alias(HttpClient::class, ArtaxClient::class);
$injector->alias(OpenIdAuthenticator::class, StackExchangeAuthenticator::class);

$injector->define(FileAdminStorage::class, [":dataFile" => __DIR__ . "/../data/admins.%s.json"]);
$injector->define(FileBanStorage::class, [":dataFile" => __DIR__ . "/../data/bans.%s.json"]);

$injector->share(AdminStorage::class);
$injector->share(BanStorage::class);
$injector->share(BitlyClient::class);
$injector->share(ChatClient::class);
$injector->share(ChatRoomCollection::class);
$injector->share(ChatRoomConnector::class);
$injector->share(CredentialManager::class);
$injector->share(EventFilterBuilder::class);
$injector->share(HttpClient::class);
$injector->share(Logger::class);
$injector->share(OpenIdAuthenticator::class);
$injector->share(PluginManager::class);
$injector->share(UriFactory::class);
$injector->share(WebSocketCollection::class);
