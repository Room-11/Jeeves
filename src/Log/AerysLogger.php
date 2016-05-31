<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

use Aerys\Logger as AerysBaseLogger;

class AerysLogger extends AerysBaseLogger
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;

        $this->setAnsify('off');
        $this->setOutputLevel(self::LEVELS[self::INFO]);
    }

    protected function output(string $message)
    {
        $this->logger->log(Level::AERYS, $message);
    }
}
