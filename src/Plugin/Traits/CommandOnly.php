<?php  declare(strict_types=1);
namespace Room11\Jeeves\Plugin\Traits;

trait CommandOnly
{
    use NoMessageHandler, NoEventHandlers, NoDisableEnable;
}