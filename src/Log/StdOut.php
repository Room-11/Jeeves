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

    private function outputContextData(array $data)
    {
        foreach ($data as $key => $value) {
            echo $key . ': ';

            if ($value instanceof \Throwable) {
                echo "{$value}\n"; // stringified exceptions are much more useful than var_dump'd ones...
            } else {
                var_dump($value);
            }
        }
    }

    public function log($logLevel, $message, array $context = null): Promise
    {
        if (!$this->meetsLogLevel($logLevel)) {
            return new Success();
        }

        printf("{$this->format}\n", $message, (new \DateTime())->format('Y-m-d H:i:s'));

        if (!empty($context) && $this->meetsLogLevel(Level::CONTEXT)) {
            $this->outputContextData($context);
        }

        return new Success();
    }
}
