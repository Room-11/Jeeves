<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event;

use Room11\Jeeves\Chat\Client\ChatRoomContainer;

interface RoomSourcedEvent extends Event, ChatRoomContainer {}
