<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Amp\Artax\Client;
use Amp\Artax\FormBody;
use Amp\Artax\Request;
use Amp\Pause;
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

    public function requestMulti(array $urisAndRequests): array {
        $response = $this->httpClient->requestMulti($urisAndRequests);

        return $response;
    }

    public function getMessage(int $id): \Generator {
        $uri = sprintf(
            "%s://%s/message/%d",
            $this->room->getHost()->isSecure() ? "https" : "http",
            $this->room->getHost()->getHostname(),
            $id
        );

        return yield $this->httpClient->request($uri);
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

        $response = yield $this->httpClient->request($request);

        if ($this->fuckOff($response->getBody())) {
            yield new Pause($this->fuckOff($response->getBody()));

            $response = yield $this->httpClient->request($request);

            $response = json_decode($response->getBody(), true);

            return new Response($response["id"], $response["time"]);
        } else {
            $response = json_decode($response->getBody(), true);

            return new Response($response["id"], $response["time"]);
        }
    }

    public function editMessage(int $id, string $text): \Generator {
        $body = (new FormBody)
            ->addField("text", $text)
            ->addField("fkey", $this->fkey);

        $uri = sprintf(
            "%s://%s/messages/%s",
            $this->room->getHost()->isSecure() ? "https" : "http",
            $this->room->getHost()->getHostname(),
            $id
        );

        $request = (new Request)
            ->setUri($uri)
            ->setMethod("POST")
            ->setBody($body);

        $response = yield $this->httpClient->request($request);

        if ($this->fuckOff($response->getBody())) {
            yield new Pause($this->fuckOff($response->getBody()));

            yield $this->httpClient->request($request);
        }
    }

    private function fuckOff(string $body): int
    {
        if (preg_match('/You can perform this action again in (\d+) seconds/', $body, $matches)) {
            return ($matches[1] + 1) * 1000;
        }

        return 0;
    }
}
