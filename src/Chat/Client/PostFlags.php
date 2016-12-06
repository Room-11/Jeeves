<?php

namespace Room11\Jeeves\Chat\Client;

class PostFlags
{
    const NONE          = 0b00000000;
    const FIXED_FONT    = 0b00000001;
    const ALLOW_PINGS   = 0b00000010;
    const ALLOW_REPLIES = 0000000100;
    const SINGLE_LINE   = 0000001000;
    const TRUNCATE      = 0000011000; // truncate implies single line
    const FORCE         = 0000100000; // override isApproved
}
