<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

abstract class BaseLogger implements Logger
{
    protected $logLevel;

    public function __construct(int $logLevel)
    {
        $this->logLevel = $logLevel;
    }

    protected function meetsLogLevel(int $messageLogLevel): bool
    {
        return (bool) ($this->logLevel & $messageLogLevel);
    }
}
