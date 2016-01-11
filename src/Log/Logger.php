<?php

namespace Room11\Jeeves\Log;

interface Logger
{
    public function log(int $level, string $message);
}
