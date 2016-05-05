<?php

namespace Room11\Jeeves\Chat\Room;

use Room11\Jeeves\Fkey\FKey;

class RoomFactory
{
    public function build(RoomIdentifier $identifier, FKey $fKey, string $webSocketURL, string $mainSiteURL)
    {
        return new Room($identifier, $fKey, $webSocketURL, $mainSiteURL);
    }
}
