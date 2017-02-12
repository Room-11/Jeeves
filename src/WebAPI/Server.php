<?php

namespace Room11\Jeeves\WebAPI;

use Aerys\Request as AerysRequest;
use Aerys\Response as AerysResponse;
use Aerys\Router as AerysRouter;
use ExceptionalJSON\DecodeErrorException as JSONDecodeErrorException;
use Room11\Jeeves\Chat\Room\ConnectedRoomCollection as ChatRoomCollection;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\Ban as BanStorage;
use const Room11\Jeeves\DNS_NAME_EXPR;
use function Aerys\router;

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

    /**
     * @param AerysResponse $response
     * @param array $route
     * @return ChatRoom|null
     */
    private function getRoomFromRoute(AerysResponse $response, array $route)
    {
        $identifier = strtolower($route['site']) . '#' . $route['roomid'];

        if (!$this->chatRooms->contains($identifier)) {
            $this->respondWithError($response, 404, 'Invalid room identifier');
            return null;
        }

        return $this->chatRooms->get($identifier);
    }

    private function getJsonRequestBody(AerysRequest $request, AerysResponse $response)
    {
        if ($request->getHeader('Content-Type') !== 'application/json') {
            $this->respondWithError($response, 400, 'Type of request body must be application/json');
            return null;
        }

        $body = $request->getBody();
        $bodyText = '';

        while (yield $body->valid()) {
            $bodyText .= $body->consume();
        }

        try {
            $json = json_try_decode($bodyText, true);
        } catch (JSONDecodeErrorException $e) {
            $this->respondWithError($response, 400, 'Unable to decode request body as application/json');
            return null;
        }

        if (!is_array($json)) {
            $this->respondWithError($response, 400, 'Invalid request data structure');
            return null;
        }

        return $json;
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
        if (!$room = $this->getRoomFromRoute($response, $route)) {
            return;
        }

        $bans = yield $this->banStorage->getAll($room);

        $response->setHeader('Content-Type', 'application/json');
        $response->end(json_encode((object)$bans));
    }

    public function banUserInRoom(AerysRequest $request, AerysResponse $response, array $route)
    {
        if (!$room = $this->getRoomFromRoute($response, $route)) {
            return;
        }

        if (!$body = yield from $this->getJsonRequestBody($request, $response)) {
            return;
        }

        if (!isset($body['user_id'], $body['duration']) || !is_int($body['user_id'])) {
            $this->respondWithError($response, 400, 'Invalid request data structure');
            return;
        }

        yield $this->banStorage->add($room, $body['user_id'], (string)$body['duration']);
        $bans = yield $this->banStorage->getAll($room);

        if (!isset($bans[$body['user_id']])) {
            $this->respondWithError($response, 500, 'Storing ban entry failed, check duration specification');
            return;
        }

        $response->setHeader('Content-Type', 'application/json');
        $response->end(json_encode(['ban_expires' => $bans[$body['user_id']]]));
    }

    public function unbanUserInRoom(AerysRequest $request, AerysResponse $response, array $route)
    {
        if (!$room = $this->getRoomFromRoute($response, $route)) {
            return;
        }

        if (!$body = yield from $this->getJsonRequestBody($request, $response)) {
            return;
        }

        if (!isset($body['user_id']) || !is_int($body['user_id'])) {
            $this->respondWithError($response, 400, 'Invalid request data structure');
            return;
        }

        yield $this->banStorage->remove($room, $body['user_id']);
        $bans = yield $this->banStorage->getAll($room);

        if (isset($bans[$body['user_id']])) {
            $this->respondWithError($response, 500, 'Removing ban entry failed');
            return;
        }

        $response->end();
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
        if (!$room = $this->getRoomFromRoute($response, $route)) {
            return;
        }

        $response->setHeader('Content-Type', 'application/json');
        $response->end(json_encode([
            'identifier' => [
                'host' => $room->getIdentifier()->getHost(),
                'room_id' => $room->getIdentifier()->getId(),
            ],
            'session' => [
                'main_site_url' => $room->getSession()->getMainSiteUrl(),
                'user_id' => $room->getSession()->getUser()->getId(),
            ],
        ]));
    }
}
