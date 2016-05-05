<?php

namespace Room11\Jeeves\Chat\Room;

class RoomFactory
{
    public function build(RoomIdentifier $identifier, string $fKey, string $webSocketURL, string $mainSiteURL)
    {
        return new Room($identifier, $fKey, $webSocketURL, $mainSiteURL);
    }
}
