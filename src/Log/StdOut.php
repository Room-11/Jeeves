<?php

namespace Room11\Jeeves\Log;

class StdOut extends BaseLogger
{
    public function log(int $logLevel, string $message, $extraData = null)
    {
        if ($this->meetsLogLevel($logLevel)) {
            return;
        }

        echo sprintf('[%s] %s', (new \DateTime())->format('Y-m-d H:i:s'), $message);

        if ($extraData !== null) {
            var_dump($extraData);
        }
    }
}
