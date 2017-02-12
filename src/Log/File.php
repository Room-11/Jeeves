<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

use Amp\Deferred;
use Amp\File\Handle;
use Amp\Promise;
use Amp\Success;
use Ds\Queue;
use function Amp\File\open as openFile;
use function Amp\resolve;

class File extends BaseLogger
{
    /** @var Handle */
    private $handle;

    private $writeQueue = false;
    private $haveWriteLoop = false;

    public function __construct(int $logLevel, string $filename)
    {
        parent::__construct($logLevel);

        $this->handle = openFile($filename, 'a');
        $this->writeQueue = new Queue;
    }

    private function writeMessagesFromQueue()
    {
        $this->haveWriteLoop = true;

        if ($this->handle instanceof Promise) {
            $this->handle = yield $this->handle;
        }

        while ($this->writeQueue->count()) {
            /** @var Deferred $deferred */
            list($timestamp, $messages, $deferred) = $this->writeQueue->pop();

            try {
                foreach ($messages as $message) {
                    yield $this->handle->write(sprintf("[%s] %s\n", $timestamp, $message));
                }

                $deferred->succeed();
            } catch (\Throwable $e) {
                $deferred->fail($e);
            }
        }

        $this->haveWriteLoop = false;
    }

    public function log(int $logLevel, string $message, $extraData = null): Promise
    {
        if (!$this->meetsLogLevel($logLevel)) {
            return new Success();
        }

        $messages = [$message];
        if ($extraData !== null && $this->meetsLogLevel(Level::EXTRA_DATA)) {
            $messages[] = json_encode($extraData);
        }

        $this->writeQueue->push([(new \DateTime)->format('Y-m-d H:i:s'), $messages, $deferred = new Deferred]);

        if (!$this->haveWriteLoop) {
            resolve($this->writeMessagesFromQueue());
        }

        return $deferred->promise();
    }
}
