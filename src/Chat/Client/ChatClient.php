<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Amp\Artax\Client;
use Amp\Artax\FormBody;
use Amp\Artax\Request;
use Room11\Jeeves\Chat\Room\Room;
use Room11\Jeeves\Fkey\FKey;

class ChatClient {
    private $httpClient;
    private $fkey;
    private $room;

    public function __construct(Client $httpClient, FKey $fkey, Room $room) {
        $this->httpClient = $httpClient;
        $this->fkey = $fkey;
        $this->room = $room;
    }

    public function request($uriOrRequest): \Generator {
        $response = yield $this->httpClient->request($uriOrRequest);

        return $response;
    }

    public function postMessage(string $text): \Generator {
        $body = (new FormBody)
            ->addField("text", $text)
            ->addField("fkey", $this->fkey);

        $uri = sprintf(
            "%s://%s/chats/%d/messages/new",
            $this->room->getHost()->isSecure() ? "https" : "http",
            $this->room->getHost()->getHostname(),
            $this->room->getId()
        );

        $request = (new Request)
            ->setUri($uri)
            ->setMethod("POST")
            ->setBody($body);

        yield $this->httpClient->request($request);
    }
}