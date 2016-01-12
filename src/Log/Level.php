<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

class Level
{
    const ERROR           = 1;
    const UNKNOWN_MESSAGE = 2;
    const MESSAGE         = 4;
    const DEBUG           = 8;
    const EXTRA_DATA      = 16;
    const ALL             = 23;
}
