<?php

namespace Room11\Jeeves;

use Amp\Artax\Client as ArtaxClient;
use Amp\Artax\Cookie\ArrayCookieJar;
use Amp\Artax\Cookie\CookieJar;
use Amp\Artax\HttpClient;
use Auryn\Injector;
use DaveRandom\AsyncBitlyClient\Client as BitlyClient;
use DaveRandom\AsyncBitlyClient\LinkAccessor as BitlyLinkAccessor;
use PeeHaa\AsyncTwitter\Http\Artax as ArtaxTwitterClient;
use PeeHaa\AsyncTwitter\Http\Client as TwitterClient;
use Room11\Jeeves\Chat\Auth\Authenticator as ChatRoomConnector;
use Room11\Jeeves\Chat\Client\Actions\ActionFactory;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\MessageResolver;
use Room11\Jeeves\Chat\Client\PostedMessageTracker;
use Room11\Jeeves\Chat\EndpointURLResolver;
use Room11\Jeeves\Chat\Event\Filter\Builder as EventFilterBuilder;
use Room11\Jeeves\Chat\Room\ConnectedRoomCollection;
use Room11\Jeeves\Chat\Room\CredentialManager as ChatRoomCredentialManager;
use Room11\Jeeves\Chat\Room\IdentifierFactory as ChatRoomIdentifierFactory;
use Room11\Jeeves\Chat\Room\PresenceManager as ChatRoomPresenceManager;
use Room11\Jeeves\Chat\Room\RoomFactory as ChatRoomFactory;
use Room11\Jeeves\Chat\Room\StatusManager as ChatRoomStatusManager;
use Room11\Jeeves\Chat\WebSocket\EventDispatcher as WebSocketEventDispatcher;
use Room11\Jeeves\Chat\WebSocket\HandlerFactory as WebSocketHandlerFactory;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\Storage\CommandAlias as CommandAliasStorage;
use Room11\Jeeves\Storage\File\Admin as FileAdminStorage;
use Room11\Jeeves\Storage\File\Ban as FileBanStorage;
use Room11\Jeeves\Storage\File\Mute as MuteStorage;
use Room11\Jeeves\Storage\File\CommandAlias as FileCommandAliasStorage;
use Room11\Jeeves\Storage\File\JsonFileAccessor;
use Room11\Jeeves\Storage\File\KeyValueFactory as FileKeyValueStorageFactory;
use Room11\Jeeves\Storage\File\Plugin as FilePluginStorage;
use Room11\Jeeves\Storage\File\Room as FileRoomStorage;
use Room11\Jeeves\Storage\KeyValueFactory as KeyValueStorageFactory;
use Room11\Jeeves\Storage\Plugin as PluginStorage;
use Room11\Jeeves\Storage\Room as RoomStorage;
use Room11\Jeeves\System\BuiltInActionManager;
use Room11\Jeeves\System\PluginManager;
use Room11\Jeeves\WebAPI\Server as WebAPIServer;
use Room11\OpenId\Authenticator as OpenIdAuthenticator;
use Room11\OpenId\StackExchangeAuthenticator;

$injector = new Injector();

/** @var Injector $injector */
$injector->alias(HttpClient::class, ArtaxClient::class);
$injector->alias(OpenIdAuthenticator::class, StackExchangeAuthenticator::class);
$injector->alias(CookieJar::class, ArrayCookieJar::class);
$injector->alias(TwitterClient::class, ArtaxTwitterClient::class);

$injector->define(FileAdminStorage::class, [":dataFile" => DATA_BASE_DIR . "/admins.%s.json"]);
$injector->define(FileBanStorage::class, [":dataFile" => DATA_BASE_DIR . "/bans.%s.json"]);
$injector->define(MuteStorage::class, [":dataFile" => DATA_BASE_DIR . "/mutes.json"]);
$injector->define(FileCommandAliasStorage::class, [":dataFile" => DATA_BASE_DIR . "/alias.%s.json"]);
$injector->define(FilePluginStorage::class, [":dataFile" => DATA_BASE_DIR . "/plugins.%s.json"]);
$injector->define(FileKeyValueStorageFactory::class, [":dataFileTemplate" => DATA_BASE_DIR . "/keyvalue.%s.json"]);
$injector->define(FileRoomStorage::class, [":dataFile" => DATA_BASE_DIR . "/rooms.json"]);

$injector->share(ActionFactory::class);
$injector->share(AdminStorage::class);
$injector->share(BanStorage::class);
$injector->share(MuteStorage::class);
$injector->share(BitlyClient::class);
$injector->share(BitlyLinkAccessor::class);
$injector->share(BuiltInActionManager::class);
$injector->share(ChatClient::class);
$injector->share(CommandAliasStorage::class);
$injector->share(ConnectedRoomCollection::class);
$injector->share(EndpointURLResolver::class);
$injector->share(ChatRoomConnector::class);
$injector->share(ChatRoomCredentialManager::class);
$injector->share(ChatRoomFactory::class);
$injector->share(ChatRoomIdentifierFactory::class);
$injector->share(ChatRoomPresenceManager::class);
$injector->share(ChatRoomStatusManager::class);
$injector->share(CookieJar::class);
$injector->share(EventFilterBuilder::class);
$injector->share(HttpClient::class);
$injector->share(JsonFileAccessor::class);
$injector->share(KeyValueStorageFactory::class);
$injector->share(Logger::class);
$injector->share(MessageResolver::class);
$injector->share(OpenIdAuthenticator::class);
$injector->share(PluginManager::class);
$injector->share(PluginStorage::class);
$injector->share(PostedMessageTracker::class);
$injector->share(RoomStorage::class);
$injector->share(WebAPIServer::class);
$injector->share(WebSocketEventDispatcher::class);
$injector->share(WebSocketHandlerFactory::class);

return $injector;
