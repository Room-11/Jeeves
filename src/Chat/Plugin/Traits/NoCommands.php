<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\Plugin\Traits;

class NoCommands
{
    public function getCommandEndpoints(): array { return []; }
}
