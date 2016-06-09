<?php  declare(strict_types=1);
namespace Room11\Jeeves\Plugin\Traits;

trait NoEventHandlers
{
    public function getEventHandlers(): array { return []; }
}
