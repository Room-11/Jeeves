<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

abstract class BaseLogger
{
    protected $logLevel;

    public function __construct(int $logLevel)
    {
        $this->logLevel = $logLevel;
    }

    protected function meetsLogLevel(int $messageLogLevel): bool
    {
        return $this->logLevel & $messageLogLevel;
    }
}
