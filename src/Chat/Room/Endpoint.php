<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\Room;

abstract class Endpoint
{
    const UI                 = 101;
    const WEBSOCKET_AUTH     = 102;
    const EVENT_HISTORY      = 103;

    const GET_MESSAGE        = 201;
    const POST_MESSAGE       = 202;
    const EDIT_MESSAGE       = 203;

    const INFO_GENERAL       = 301;
    const INFO_STARS         = 302;
    const INFO_CONVERSATIONS = 303;
    const INFO_SCHEDULE      = 304;
    const INFO_FEEDS         = 305;
    const INFO_ACCESS        = 306;
}
