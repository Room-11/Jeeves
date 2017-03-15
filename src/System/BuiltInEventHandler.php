<?php declare(strict_types = 1);

namespace Room11\Jeeves\System;

use Amp\Promise;
use Room11\Jeeves\Chat\Event\Event;

interface BuiltInEventHandler
{
    /**
     * Handle an event
     *
     * @param Event $event
     * @return Promise
     */
    function handleEvent(Event $event): Promise;

    /**
     * Get a list of event type IDs handled by this built-in
     *
     * @return int[]
     */
    function getEventTypes(): array;
}
