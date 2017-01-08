<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat;

use Room11\Jeeves\Chat\Room\ConnectedRoomCollection;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class EndpointURLResolver
{
    private static $endpointURLTemplates = [
        Endpoint::CHATROOM_UI                  => 'https://%1$s/rooms/%2$d',
        Endpoint::CHATROOM_WEBSOCKET_AUTH      => 'https://%1$s/ws-auth',
        Endpoint::CHATROOM_EVENT_HISTORY       => 'https://%1$s/chats/%2$d/events',
        Endpoint::CHATROOM_STARS_LIST          => 'https://%1$s/chats/stars/%2$d?count=0',

        Endpoint::CHATROOM_GET_MESSAGE_HTML    => 'https://%1$s/message/%3$d',
        Endpoint::CHATROOM_POST_MESSAGE        => 'https://%1$s/chats/%2$d/messages/new',
        Endpoint::CHATROOM_EDIT_MESSAGE        => 'https://%1$s/messages/%3$d',
        Endpoint::CHATROOM_MOVE_MESSAGE        => 'https://%1$s/admin/movePosts/%2$d',
        Endpoint::CHATROOM_PIN_MESSAGE         => 'https://%1$s/messages/%3$d/owner-star',
        Endpoint::CHATROOM_UNSTAR_MESSAGE      => 'https://%1$s/messages/%3$d/unstar',
        Endpoint::CHATROOM_GET_MESSAGE_TEXT    => 'https://%1$s/messages/%2$d/%3$d',
        Endpoint::CHATROOM_GET_MESSAGE_HISTORY => 'https://%1$s/messages/%3$d/history',
        Endpoint::CHATROOM_LEAVE               => 'https://%1$s/chats/leave/%2$d',

        Endpoint::CHATROOM_INFO_GENERAL        => 'https://%1$s/rooms/info/%2$d?tab=general',
        Endpoint::CHATROOM_INFO_STARS          => 'https://%1$s/rooms/info/%2$d?tab=stars',
        Endpoint::CHATROOM_INFO_CONVERSATIONS  => 'https://%1$s/rooms/info/%2$d?tab=conversations',
        Endpoint::CHATROOM_INFO_SCHEDULE       => 'https://%1$s/rooms/info/%2$d?tab=schedule',
        Endpoint::CHATROOM_INFO_FEEDS          => 'https://%1$s/rooms/info/%2$d?tab=feeds',
        Endpoint::CHATROOM_INFO_ACCESS         => 'https://%1$s/rooms/info/%2$d?tab=access',
        Endpoint::CHATROOM_INFO_PINGABLE       => 'https://%1$s/rooms/pingable/%2$d',

        Endpoint::CHAT_USER                    => 'https://%1$s/users/%3$d',
        Endpoint::CHAT_USER_INFO               => 'https://%1$s/user/info',
        Endpoint::CHAT_USER_INFO_EXTRA         => 'https://%1$s/users/thumbs/%3$d?showUsage=false',

        Endpoint::MAINSITE_USER                => '%1$s/users/%2$d?tab=profile',
    ];

    private $connectedRooms;

    private function getIdentifierFromArg($arg): ChatRoomIdentifier
    {
        if ($arg instanceof ChatRoom) {
            return $arg->getIdentifier();
        } else if ($arg instanceof ChatRoomIdentifier) {
            return $arg;
        }

        throw new \InvalidArgumentException('Invalid chat room identifier');
    }

    private function getRoomFromArg($arg): ChatRoom
    {
        if ($arg instanceof ChatRoom) {
            return $arg;
        } else if ($arg instanceof ChatRoomIdentifier || is_string($arg)) {
            return $this->connectedRooms->get($arg);
        }

        throw new \InvalidArgumentException('Invalid chat room identifier');
    }

    private function getChatEndpointURL(ChatRoomIdentifier $identifier, int $endpoint, array $extraData): string
    {
        return sprintf(
            self::$endpointURLTemplates[$endpoint],
            $identifier->getHost(),
            $identifier->getId(),
            ...$extraData
        );
    }

    private function getMainSiteEndpointURL(ChatRoom $room, int $endpoint, array $extraData): string
    {
        return sprintf(
            self::$endpointURLTemplates[$endpoint],
            rtrim($room->getSession()->getMainSiteUrl(), '/'),
            ...$extraData
        );
    }

    public function __construct(ConnectedRoomCollection $connectedRooms)
    {
        $this->connectedRooms = $connectedRooms;
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier|string $room
     * @param int $endpoint
     * @param array $extraData
     * @return string
     */
    public function getEndpointURL($room, int $endpoint, ...$extraData): string
    {
        if (!isset(self::$endpointURLTemplates[$endpoint])) {
            throw new \LogicException('Invalid endpoint ID');
        }

        if ($endpoint < Endpoint::MAINSITE_URLS_START) {
            return $this->getChatEndpointURL($this->getIdentifierFromArg($room), $endpoint, $extraData);
        }

        return $this->getMainSiteEndpointURL($this->getRoomFromArg($room), $endpoint, $extraData);
    }
}
