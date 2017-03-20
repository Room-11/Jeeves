<?php

$injector = new Auryn\Injector();

$injector->alias(Amp\Artax\HttpClient::class, Amp\Artax\Client::class);
$injector->alias(Amp\Artax\Cookie\CookieJar::class, Amp\Artax\Cookie\ArrayCookieJar::class);
$injector->alias(PeeHaa\AsyncTwitter\Http\Client::class, PeeHaa\AsyncTwitter\Http\Artax::class);
$injector->alias(Room11\OpenId\Authenticator::class, Room11\OpenId\StackExchangeAuthenticator::class);
$injector->alias(Room11\StackChat\Client\Client::class, Room11\StackChat\Client\ChatClient::class);
$injector->alias(Room11\StackChat\Auth\SessionTracker::class, Room11\StackChat\Auth\ActiveSessionTracker::class);
$injector->alias(Room11\StackChat\Client\TextFormatter::class, Room11\StackChat\Client\PostTextFormatter::class);
$injector->alias(Room11\StackChat\Room\AclDataAccessor::class, Room11\StackChat\Room\ChatRoomAclDataAccessor::class);
$injector->alias(Room11\StackChat\Room\PostPermissionManager::class, Room11\Jeeves\Chat\RoomStatusManager::class);
$injector->alias(Room11\StackChat\WebSocket\EventDispatcher::class, Room11\Jeeves\Chat\WebSocketEventDispatcher::class);

$injector->define(Room11\Jeeves\Storage\File\Admin::class, [":dataFile" => Room11\Jeeves\DATA_BASE_DIR . "/admins.%s.json"]);
$injector->define(Room11\Jeeves\Storage\File\Ban::class, [":dataFile" => Room11\Jeeves\DATA_BASE_DIR . "/bans.%s.json"]);
$injector->define(Room11\Jeeves\Storage\File\CommandAlias::class, [":dataFile" => Room11\Jeeves\DATA_BASE_DIR . "/alias.%s.json"]);
$injector->define(Room11\Jeeves\Storage\File\Plugin::class, [":dataFile" => Room11\Jeeves\DATA_BASE_DIR . "/plugins.%s.json"]);
$injector->define(Room11\Jeeves\Storage\File\KeyValueFactory::class, [":dataFileTemplate" => Room11\Jeeves\DATA_BASE_DIR . "/keyvalue.%s.json"]);
$injector->define(Room11\Jeeves\Storage\File\Room::class, [":dataFile" => Room11\Jeeves\DATA_BASE_DIR . "/rooms.json"]);

$injector->share(Amp\Artax\HttpClient::class);
$injector->share(Amp\Artax\Cookie\CookieJar::class);
$injector->share(DaveRandom\AsyncBitlyClient\Client::class);
$injector->share(DaveRandom\AsyncBitlyClient\LinkAccessor::class);
$injector->share(Psr\Log\LoggerInterface::class);
$injector->share(Room11\Jeeves\Chat\EventFilter\Builder::class);
$injector->share(Room11\Jeeves\Chat\PresenceManager::class);
$injector->share(Room11\Jeeves\Chat\RoomStatusManager::class);
$injector->share(Room11\Jeeves\Chat\WebSocketEventDispatcher::class);
$injector->share(Room11\Jeeves\Storage\Admin::class);
$injector->share(Room11\Jeeves\Storage\Ban::class);
$injector->share(Room11\Jeeves\Storage\CommandAlias::class);
$injector->share(Room11\Jeeves\Storage\KeyValueFactory::class);
$injector->share(Room11\Jeeves\Storage\Plugin::class);
$injector->share(Room11\Jeeves\Storage\Room::class);
$injector->share(Room11\Jeeves\Storage\File\JsonFileAccessor::class);
$injector->share(Room11\Jeeves\System\BuiltInActionManager::class);
$injector->share(Room11\Jeeves\System\PluginManager::class);
$injector->share(Room11\Jeeves\WebAPI\Server::class);
$injector->share(Room11\OpenId\Authenticator::class);
$injector->share(Room11\StackChat\EndpointURLResolver::class);
$injector->share(Room11\StackChat\Auth\ActiveSessionTracker::class);
$injector->share(Room11\StackChat\Auth\Authenticator::class);
$injector->share(Room11\StackChat\Auth\CredentialManager::class);
$injector->share(Room11\StackChat\Auth\SessionTracker::class);
$injector->share(Room11\StackChat\Client\Client::class);
$injector->share(Room11\StackChat\Client\MessageResolver::class);
$injector->share(Room11\StackChat\Client\PostedMessageTracker::class);
$injector->share(Room11\StackChat\Client\TextFormatter::class);
$injector->share(Room11\StackChat\Client\Actions\Factory::class);
$injector->share(Room11\StackChat\Room\ConnectedRoomCollection::class);
$injector->share(Room11\StackChat\Room\AclDataAccessor::class);
$injector->share(Room11\StackChat\Room\IdentifierFactory::class);
$injector->share(Room11\StackChat\WebSocket\EndpointCollection::class);
$injector->share(Room11\StackChat\WebSocket\HandlerFactory::class);

return $injector;
