<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

class StdOut extends BaseLogger
{
    public function log(int $logLevel, string $message, $extraData = null)
    {
        if (!$this->meetsLogLevel($logLevel)) {
            return;
        }

        echo sprintf("[%s] %s\n", (new \DateTime())->format('Y-m-d H:i:s'), $message);

        if ($extraData !== null && $this->meetsLogLevel(Level::EXTRA_DATA)) {
            var_dump($extraData);
        }
    }
}
