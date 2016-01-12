<?php declare(strict_types=1);

namespace Room11\Jeeves;

use Room11\Jeeves\OpenId\Credentials;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\NullLogger;

// https://openid.stackexchange.com/account/login
$openIdCredentials = new Credentials('openidusername', 'openidpassword');

$logger = new NullLogger(Level::NONE);
