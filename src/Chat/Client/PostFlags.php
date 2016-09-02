<?php

namespace Room11\Jeeves\Chat\Client;

class PostFlags
{
    const NONE        = 0b00000000;
    const FIXED_FONT  = 0b00000001;
    const ALLOW_PINGS = 0b00000010;
    const SINGLE_LINE = 0b00000100;
    const TRUNCATE    = 0b00001100; // truncate implies single line
}
