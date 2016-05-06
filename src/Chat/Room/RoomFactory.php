<?php

namespace Room11\Jeeves\Chat\Room;

class RoomFactory
{
    public function build(Identifier $identifier, string $fKey, string $webSocketURL, string $mainSiteURL)
    {
        return new Room($identifier, $fKey, $webSocketURL, $mainSiteURL);
    }
}
