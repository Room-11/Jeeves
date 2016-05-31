<?php

namespace Room11\Jeeves\WebAPI;

use Aerys\Request as AerysRequest;
use Aerys\Response as AerysResponse;
use Aerys\Router as AerysRouter;
use Room11\Jeeves\Chat\Room\Collection as ChatRoomCollection;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use const Room11\Jeeves\DNS_NAME_EXPR;
use function Aerys\router;
use Room11\Jeeves\Storage\Ban as BanStorage;

class Server
{
    const ROOM_IDENTIFIER_EXPR = '{site:' . DNS_NAME_EXPR . '}/{roomid:[0-9]+}';

    private $router;

    private $chatRooms;
    private $banStorage;

    private function buildRouter()
    {
        $this->router = router()
            ->route('GET', '/rooms', [$this, 'getAllRooms'])
            ->route('GET', '/rooms/' . self::ROOM_IDENTIFIER_EXPR, [$this, 'getRoomInfo'])
            ->route('GET', '/rooms/' . self::ROOM_IDENTIFIER_EXPR . '/bans', [$this, 'getRoomBans'])
            ->route('POST', '/rooms/' . self::ROOM_IDENTIFIER_EXPR . '/ban', [$this, 'banUserInRoom'])
            ->route('POST', '/rooms/' . self::ROOM_IDENTIFIER_EXPR . '/unban', [$this, 'unbanUserInRoom']);
    }

    private function getRoomIdentifierFromRoute(array $route)
    {
        return strtolower($route['site']) . '#' . $route['roomid'];
    }

    private function respondWithError(AerysResponse $response, int $responseCode, string $message)
    {
        $response->setStatus($responseCode);
        $response->setHeader('Content-Type', 'application/json');
        $response->end(json_encode(['error' => $message]));
    }

    public function __construct(ChatRoomCollection $chatRooms, BanStorage $banStorage)
    {
        $this->buildRouter();

        $this->chatRooms = $chatRooms;
        $this->banStorage = $banStorage;
    }

    public function getRouter(): AerysRouter
    {
        return $this->router;
    }

    public function getRoomBans(AerysRequest $request, AerysResponse $response, array $route)
    {
        $roomIdentifier = $this->getRoomIdentifierFromRoute($route);

        if (!$this->chatRooms->contains($roomIdentifier)) {
            $this->respondWithError($response, 404, 'Invalid room identifier');
            return;
        }

        $room = $this->chatRooms->get($roomIdentifier);

        $bans = yield $this->banStorage->getAll($room);

        $response->setHeader('Content-Type', 'application/json');
        $response->end(json_encode((object)$bans));
    }

    public function banUserInRoom(AerysRequest $request, AerysResponse $response, array $route)
    {
        $roomIdentifier = $this->getRoomIdentifierFromRoute($route);

        if (!$this->chatRooms->contains($roomIdentifier)) {
            $this->respondWithError($response, 404, 'Invalid room identifier');
            return;
        }

        $room = $this->chatRooms->get($roomIdentifier);
    }

    public function unbanUserInRoom(AerysRequest $request, AerysResponse $response, array $route)
    {
        $roomIdentifier = $this->getRoomIdentifierFromRoute($route);

        if (!$this->chatRooms->contains($roomIdentifier)) {
            $this->respondWithError($response, 404, 'Invalid room identifier');
            return;
        }

        $room = $this->chatRooms->get($roomIdentifier);
    }

    public function getAllRooms(AerysRequest $request, AerysResponse $response)
    {
        $result = [];

        /** @var ChatRoom $room */
        foreach ($this->chatRooms as $room) {
            $result[] = [
                'host' => $room->getIdentifier()->getHost(),
                'room_id' => $room->getIdentifier()->getId(),
            ];
        }

        $response->setHeader('Content-Type', 'application/json');
        $response->end(json_encode($result));
    }

    public function getRoomInfo(AerysRequest $request, AerysResponse $response, array $route)
    {
        $roomIdentifier = $this->getRoomIdentifierFromRoute($route);

        if (!$this->chatRooms->contains($roomIdentifier)) {
            $this->respondWithError($response, 404, 'Invalid room identifier');
            return;
        }

        $room = $this->chatRooms->get($roomIdentifier);

        $response->setHeader('Content-Type', 'application/json');
        $response->end(json_encode([
            'identifier' => [
                'host' => $room->getIdentifier()->getHost(),
                'room_id' => $room->getIdentifier()->getId(),
                'secure' => $room->getIdentifier()->isSecure(),
            ],
            'session' => [
                'main_site_url' => $room->getSessionInfo()->getMainSiteUrl(),
                'user_id' => $room->getSessionInfo()->getUserId(),
            ],
        ]));
    }
}
