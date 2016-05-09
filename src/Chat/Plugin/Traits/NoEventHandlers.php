<?php

namespace Room11\Jeeves\Chat\Plugin\Traits;

trait NoEventHandlers
{
    public function getEventHandlers(): array { return []; }
}
