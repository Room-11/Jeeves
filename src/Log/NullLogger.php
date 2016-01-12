<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

class NullLogger extends BaseLogger
{
    public function log(int $logLevel, string $message, $extraData = null)
    {
    }
}
