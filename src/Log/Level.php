<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

abstract class Level
{
    const EMERGENCY = 0x00000001;
    const ALERT     = 0x00000002;
    const CRITICAL  = 0x00000004;
    const ERROR     = 0x00000008;
    const WARNING   = 0x00000010;
    const NOTICE    = 0x00000020;
    const INFO      = 0x00000040;
    const DEBUG     = 0x40000000;
    const CONTEXT   = 0x80000000;

    const ALL           = ~0 & ~(self::DEBUG | self::CONTEXT);
    const NONE          = 0;

    private function __construct() {}
}
