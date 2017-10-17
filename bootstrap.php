<?php declare(strict_types = 1);

namespace Room11\Jeeves;

const GITHUB_PROJECT_URL = 'https://github.com/Room-11/Jeeves';
const APP_BASE = __DIR__;
const DATA_BASE_DIR = APP_BASE . '/data';

define(__NAMESPACE__ . '\\PROCESS_START_TIME', time());
define(__NAMESPACE__ . '\\IS_WINDOWS', stripos(PHP_OS, 'win') === 0);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/version.php';
