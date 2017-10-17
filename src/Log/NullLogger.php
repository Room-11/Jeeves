<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

use Amp\Promise;
use Amp\Success;

class NullLogger extends BaseLogger
{
    public function log($logLevel, $message, array $extraData = null): Promise
    {
        return new Success();
    }
}
