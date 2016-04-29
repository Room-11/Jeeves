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

        // @todo remove me once we found out what message this breaks on
        try {
            $response = yield $this->httpClient->request($request);

            $decoded = json_decode($response->getBody(), true);
            $decodeError = json_last_error();
            $decodeErrorStr = json_last_error();

            if ($decodeError === JSON_ERROR_NONE) {
                if (!isset($decoded["id"], $decoded["time"])) {
                    throw new \RuntimeException('Got a JSON response but it doesn\'t contain the expected data');
                }

                return new Response($decoded["id"], $decoded["time"]);
            }

            if ($this->fuckOff($response->getBody())) {
                yield new Pause($this->fuckOff($response->getBody()));

                $response = yield $this->httpClient->request($request);

                $response = json_decode($response->getBody(), true);

                return new Response($response["id"], $response["time"]);
            }

            throw new \RuntimeException(
                'A response that could not be decoded as JSON or otherwise handled was received'
                . ' (JSON decode error: ' . $decodeErrorStr . ')'
            );
        } catch (\Throwable $e) {
            $errorInfo = isset($response) ? $response->getBody() : 'No response data';

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
