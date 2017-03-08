<?php declare(strict_types = 1);

namespace Room11\Jeeves;

const GITHUB_PROJECT_URL = 'https://github.com/Room-11/Jeeves';
const DATA_BASE_DIR = __DIR__ . '/../data';

define(__NAMESPACE__ . '\\PROCESS_START_TIME', time());
define(__NAMESPACE__ . '\\IS_WINDOWS', stripos(PHP_OS, 'win') === 0);
define(__NAMESPACE__ . '\\APP_BASE', realpath(__DIR__ . '/..'));

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../version.php';
