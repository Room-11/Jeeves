<?php

namespace Room11\Jeeves\OpenId;

interface Authenticator
{
    public function logIn(string $url): \Generator;
}
