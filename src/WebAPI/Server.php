<?php

namespace Room11\Jeeves\WebAPI;

use Aerys\Request as AerysRequest;
use Aerys\Response as AerysResponse;
use Aerys\Router as AerysRouter;
use function Aerys\router;

class Server
{
    private $router;

    public function __construct()
    {
        $this->router = router()
            ->route('GET', '/', [$this, 'test']);
    }

    public function getRouter(): AerysRouter
    {
        return $this->router;
    }

    public function test(AerysRequest $request, AerysResponse $response)
    {
        $response->end('Hello world!');
    }
}
