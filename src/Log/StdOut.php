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

        if ($extraData !== null) {
            $this->logExtraData($extraData);
        }
    }

    private function logExtraData($extraData)
    {
        if (!$this->meetsLogLevel(Level::EXTRA_DATA) && $extraData !== null) {
            return;
        }

        var_dump($extraData);
    }
}
