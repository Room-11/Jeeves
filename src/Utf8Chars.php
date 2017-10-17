<?php declare(strict_types = 1);
/**
 * Created by PhpStorm.
 * User: chris.wright
 * Date: 19/03/2017
 * Time: 22:48
 */

namespace Room11\Jeeves;

use Room11\StackChat\Client\Utf8Chars as StackChatUtf8Chars;

final class Utf8Chars
{
    public const ELLIPSIS = StackChatUtf8Chars::ELLIPSIS;
    public const ZWNJ = StackChatUtf8Chars::ZWNJ;

    public const BULLET   = "\xE2\x80\xA2";
    public const RIGHTWARDS_ARROW = "\xE2\x86\x92";
    public const EM_SPACE = "\xE2\x80\x83";
    public const WHITE_BULLET = "\xE2\x97\xA6";

    private function __construct() {}
}
