<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

class File extends BaseLogger
{
    private $filename;
    
    public function __construct(int $logLevel, string $filename)
    {
        parent::__construct($logLevel);

        $this->filename = $filename;
    }

    public function log(int $logLevel, string $message, $extraData = null)
    {
        if (!$this->meetsLogLevel($logLevel)) {
            return;
        }

        file_put_contents(
            $this->filename,
            sprintf("[%s] %s\n", (new \DateTime())->format('Y-m-d H:i:s'), $message),
            FILE_APPEND
        );

        if ($extraData !== null && $this->meetsLogLevel(Level::EXTRA_DATA)) {
            file_put_contents(
                $this->filename,
                sprintf("[%s] %s\n", (new \DateTime())->format('Y-m-d H:i:s'), json_encode($extraData)),
                FILE_APPEND
            );
        }
    }
}
