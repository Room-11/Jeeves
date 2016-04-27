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

    public function requestMulti(array $urisAndRequests): \Generator {
        $promises = array_map(function($uriOrRequest) {
            return $this->httpClient->request($uriOrRequest);
        }, $urisAndRequests);

        $responses = [];

        foreach ($promises as $promise) {
            $responses[] = yield $promise;
        }

        return $responses;
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

        // @todo remove me once we found out what message this breaks on
        try {
            if ($this->fuckOff($response->getBody())) {
                yield new Pause($this->fuckOff($response->getBody()));

                $response = yield $this->httpClient->request($request);

                $response = json_decode($response->getBody(), true);

                return new Response($response["id"], $response["time"]);
            } else {
                $response = json_decode($response->getBody(), true);

                return new Response($response["id"], $response["time"]);
            }
        } catch (\Throwable $e) {
            if (is_array($response)) {
                $errorInfo = json_encode($response);
            } else {
                $errorInfo = $response->getBody();
            }

            file_put_contents(
                __DIR__ . '/../../../data/exceptions.txt',
                (new \DateTimeImmutable())->format('Y-m-d H:i:s') . ' ' . $e->getMessage() . "\r\n" . $errorInfo . "\r\n\r\n",
                FILE_APPEND
            );

            yield new Pause(2000);

            yield from $this->postMessage("@PeeHaa error has been logged. Fix it fix it fix it fix it.");
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
            return ($matches[1] + 2) * 1000;
        }

        return 0;
    }
}
