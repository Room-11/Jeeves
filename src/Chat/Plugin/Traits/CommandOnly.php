<?php

namespace Room11\Jeeves\Chat\Plugin\Traits;

trait CommandOnly
{
    use NoMessageHandler, NoEventHandlers;
}
