<?php

namespace Room11\Jeeves;

use Amp\Artax\Client as ArtaxClient;
use Amp\Artax\HttpClient;
use Auryn\Injector;
use Room11\Jeeves\Bitly\Client as BitlyClient;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Room\Collection as ChatRoomCollection;
use Room11\Jeeves\Fkey\Retriever as FKeyRetriever;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\OpenId\EmailAddress as OpenIdEmailAddress;
use Room11\Jeeves\OpenId\Password as OpenIdPassword;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\Storage\File\Admin as FileAdminStorage;
use Room11\Jeeves\Storage\File\Ban as FileBanStorage;

/** @var Injector $injector */
$injector->alias(HttpClient::class, ArtaxClient::class);

$injector->define(FileAdminStorage::class, [":dataFile" => __DIR__ . "/../data/admins.json"]);
$injector->define(FileBanStorage::class, [":dataFile" => __DIR__ . "/../data/bans.json"]);

$injector->share(AdminStorage::class);
$injector->share(BanStorage::class);
$injector->share(BitlyClient::class);
$injector->share(ChatClient::class);
$injector->share(ChatRoomCollection::class);
$injector->share(FKeyRetriever::class);
$injector->share(HttpClient::class);
$injector->share(Logger::class);
$injector->share(OpenIdEmailAddress::class);
$injector->share(OpenIdPassword::class);
