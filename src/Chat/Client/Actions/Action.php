<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client\Actions;

use Amp\Artax\Request as HttpRequest;
use Amp\Deferred;
use Amp\Promisor;
use Room11\Jeeves\Log\Logger;

abstract class Action implements Promisor
{
    const SUCCESS = -1;
    const FAILURE = 0;

    private $deferred;

    protected $logger;
    protected $request;

    public function __construct(Logger $logger, HttpRequest $request)
    {
        $this->logger = $logger;
        $this->request = $request;

        $this->deferred = new Deferred();
    }

    public function getRequest(): HttpRequest
    {
        return $this->request;
    }

    public function isValid(): bool
    {
        return true;
    }

    abstract public function getMaxAttempts(): int;

    abstract public function processResponse($response, int $attempt): int;

    public function promise()
    {
        return $this->deferred->promise();
    }

    public function update($progress)
    {
        $this->deferred->update($progress);
    }

    public function succeed($result = null)
    {
        $this->deferred->succeed($result);
    }

    public function fail($error)
    {
        $this->deferred->fail($error);
    }
}
