<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

use Room11\Jeeves\Chat\Room\Collection as ChatRoomCollection;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class EndpointURLResolver
{
    private static $endpointURLTemplates = [
        Endpoint::CHATROOM_UI                  => '%1$s://%2$s/rooms/%3$d',
        Endpoint::CHATROOM_WEBSOCKET_AUTH      => '%1$s://%2$s/ws-auth',
        Endpoint::CHATROOM_EVENT_HISTORY       => '%1$s://%2$s/chats/%3$d/events',
        Endpoint::CHATROOM_STARS_LIST          => '%1$s://%2$s/chats/stars/%3$d?count=0',

        Endpoint::CHATROOM_GET_MESSAGE_HTML    => '%1$s://%2$s/message/%4$d',
        Endpoint::CHATROOM_POST_MESSAGE        => '%1$s://%2$s/chats/%3$d/messages/new',
        Endpoint::CHATROOM_EDIT_MESSAGE        => '%1$s://%2$s/messages/%4$d',
        Endpoint::CHATROOM_PIN_MESSAGE         => '%1$s://%2$s/messages/%4$d/owner-star',
        Endpoint::CHATROOM_UNSTAR_MESSAGE      => '%1$s://%2$s/messages/%4$d/unstar',
        Endpoint::CHATROOM_GET_MESSAGE_TEXT    => '%1$s://%2$s/messages/%3$d/%4$d',
        Endpoint::CHATROOM_GET_MESSAGE_HISTORY => '%1$s://%2$s/messages/%4$d/history',

        Endpoint::CHATROOM_INFO_GENERAL        => '%1$s://%2$s/rooms/info/%3$d?tab=general',
        Endpoint::CHATROOM_INFO_STARS          => '%1$s://%2$s/rooms/info/%3$d?tab=stars',
        Endpoint::CHATROOM_INFO_CONVERSATIONS  => '%1$s://%2$s/rooms/info/%3$d?tab=conversations',
        Endpoint::CHATROOM_INFO_SCHEDULE       => '%1$s://%2$s/rooms/info/%3$d?tab=schedule',
        Endpoint::CHATROOM_INFO_FEEDS          => '%1$s://%2$s/rooms/info/%3$d?tab=feeds',
        Endpoint::CHATROOM_INFO_ACCESS         => '%1$s://%2$s/rooms/info/%3$d?tab=access',
        Endpoint::CHATROOM_INFO_PINGABLE       => '%1$s://%2$s/rooms/pingable/%3$d',

        Endpoint::CHAT_USER                    => '%1$s://%2$s/users/%4$d',
        Endpoint::CHAT_USER_INFO               => '%1$s://%2$s/user/info',
        Endpoint::CHAT_USER_INFO_EXTRA         => '%1$s://%2$s/users/thumbs/%4$d?showUsage=false',

        Endpoint::MAINSITE_USER                => '%1$s/users/%2$d',
    ];

    private $roomCollection;

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
            return $this->roomCollection->get($arg);
        }

        throw new \InvalidArgumentException('Invalid chat room identifier');
    }

    private function getChatEndpointURL(ChatRoomIdentifier $identifier, int $endpoint, array $extraData): string
    {
        return sprintf(
            self::$endpointURLTemplates[$endpoint],
            $identifier->isSecure() ? "https" : "http",
            $identifier->getHost(),
            $identifier->getId(),
            ...$extraData
        );
    }

    private function getMainSiteEndpointURL(ChatRoom $room, int $endpoint, array $extraData): string
    {
        return sprintf(
            self::$endpointURLTemplates[$endpoint],
            rtrim($room->getSessionInfo()->getMainSiteUrl(), '/'),
            ...$extraData
        );
    }

    public function __construct(ChatRoomCollection $roomCollection)
    {
        $this->roomCollection = $roomCollection;
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
