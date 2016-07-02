<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\Room;

abstract class Endpoint
{
    // chat auth/init
    const CHATROOM_UI                  = 101;
    const CHATROOM_WEBSOCKET_AUTH      = 102;
    const CHATROOM_EVENT_HISTORY       = 103;
    const CHATROOM_STARS_LIST          = 104;

    // chat actions
    const CHATROOM_GET_MESSAGE_HTML    = 201;
    const CHATROOM_POST_MESSAGE        = 202;
    const CHATROOM_EDIT_MESSAGE        = 203;
    const CHATROOM_PIN_MESSAGE         = 204;
    const CHATROOM_UNSTAR_MESSAGE      = 205;
    const CHATROOM_GET_MESSAGE_TEXT    = 206;
    const CHATROOM_GET_MESSAGE_HISTORY = 207;

    // chat info
    const CHATROOM_INFO_GENERAL        = 301;
    const CHATROOM_INFO_STARS          = 302;
    const CHATROOM_INFO_CONVERSATIONS  = 303;
    const CHATROOM_INFO_SCHEDULE       = 304;
    const CHATROOM_INFO_FEEDS          = 305;
    const CHATROOM_INFO_ACCESS         = 306;
    const CHAT_USER                    = 307;
    const CHAT_USER_INFO               = 308;
    const CHAT_USER_INFO_EXTRA         = 309;

    // anything >500 targets the main site

    // main site info
    const MAINSITE_USER                = 501;
}
