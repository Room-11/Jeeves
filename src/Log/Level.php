<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

class Level
{
    const ERROR         = 0x01;
    const UNKNOWN_EVENT = 0x02;
    const EVENT         = 0x04;
    const DEBUG         = 0x08;
    const EXTRA_DATA    = 0x10;
    const AERYS         = 0x20;

    const ALL           = 0xffffffff & ~self::DEBUG;
    const NONE          = 0;
}
