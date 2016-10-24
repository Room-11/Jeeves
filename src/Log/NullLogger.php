<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

use Amp\Success;
use Amp\Promise;

class NullLogger extends BaseLogger
{
    public function log(int $logLevel, string $message, $extraData = null): Promise
    {
        return new Success();
    }
}
