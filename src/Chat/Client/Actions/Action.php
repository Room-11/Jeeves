<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client\Actions;

use Amp\Artax\Request as HttpRequest;
use Amp\Promisor;
use Room11\Jeeves\Log\Logger;

abstract class Action
{
    private $request;
    private $promisor;

    const SUCCESS = -1;
    const FAILURE = 0;

    public function __construct(HttpRequest $request, Promisor $promisor)
    {
        $this->request = $request;
        $this->promisor = $promisor;
    }

    public function getRequest(): HttpRequest
    {
        return $this->request;
    }

    public function getPromisor(): Promisor
    {
        return $this->promisor;
    }

    abstract public function getMaxAttempts(): int;

    abstract public function processResponse($response, int $attempt, Logger $logger): int;
}
