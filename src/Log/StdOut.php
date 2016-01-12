<?php

namespace Room11\Jeeves\Log;

class StdOut extends BaseLogger
{
    public function log(int $logLevel, string $message, $extraData = null)
    {
        $this->logMessage($logLevel, $message);

        $this->logExtraData($extraData);
    }

    private function logMessage(int $logLevel, string $message)
    {
        if (!$this->meetsLogLevel($logLevel)) {
            return;
        }

        echo sprintf('[%s] %s', (new \DateTime())->format('Y-m-d H:i:s'), $message);
    }

    private function logExtraData($extraData)
    {
        if (!$this->meetsLogLevel(Level::EXTRA_DATA) && $extraData !== null) {
            return;
        }

        var_dump($extraData);
    }
}
