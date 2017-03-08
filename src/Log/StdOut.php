<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

use Amp\Promise;
use Amp\Success;

class StdOut extends BaseLogger
{
    private $format;

    public function __construct(int $level, bool $showTimestamps = true)
    {
        parent::__construct($level);

        $this->format = $showTimestamps
            ? '[%2$s] %1$s'
            : '%s';
    }

    public function log(int $logLevel, string $message, $extraData = null): Promise
    {
        if (!$this->meetsLogLevel($logLevel)) {
            return new Success();
        }

        printf("{$this->format}\n", $message, (new \DateTime())->format('Y-m-d H:i:s'));

        if ($extraData !== null && $this->meetsLogLevel(Level::EXTRA_DATA)) {
            if ($extraData instanceof \Throwable) {
                echo "{$extraData}\n"; // stringified exceptions are much more useful than var_dump'd ones...
            } else {
                var_dump($extraData);
            }
        }

        return new Success();
    }
}
