<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

use Amp\Promise;

interface Logger
{
    function log(int $level, string $message, $extraData = null): Promise;
}
