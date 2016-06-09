<?php  declare(strict_types=1);
namespace Room11\Jeeves\Plugins\Traits;

trait NoMessageHandler
{
    public function getMessageHandler() { return null; }
}
