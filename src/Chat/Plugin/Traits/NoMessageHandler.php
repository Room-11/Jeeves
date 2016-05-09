<?php

namespace Room11\Jeeves\Chat\Plugin\Traits;

trait NoMessageHandler
{
    public function getMessageHandler() { return null; }
}
