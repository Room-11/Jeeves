<?php declare(strict_types = 1);

namespace Room11\Jeeves\OpenId;

use Amp\Artax\Client as HttpClient;
use Amp\Artax\FormBody;
use Amp\Artax\Request;
use Amp\Artax\Response;
use Room11\Jeeves\Chat\Room\Room;
use Room11\Jeeves\Fkey\Retriever as FkeyRetriever;

class Client {
    private $credentials;
    private $httpClient;
    private $fkeyRetriever;

    public function __construct(Credentials $credentials, HttpClient $httpClient, FkeyRetriever $fkeyRetriever) {
        $this->credentials   = $credentials;
        $this->httpClient    = $httpClient;
        $this->fkeyRetriever = $fkeyRetriever;
    }

    public function logIn() {
        (new OpenIdLogin($this->credentials, $this->httpClient, $this->fkeyRetriever))->logIn();
        (new StackOverflowLogin($this->credentials, $this->httpClient, $this->fkeyRetriever))->logIn();
    }

    public function getWebSocketUri(Room $room): string {
        $origin = sprintf(
            "%s://%s",
            $room->getHost()->isSecure() ? "https" : "http",
            $room->getHost()->getHostname()
        );

        $fKey = (string)$this->fkeyRetriever->get($origin . "/rooms/" . $room->getId());

        $authBody = (new FormBody)
            ->addField("roomid", $room->getId())
            ->addField("fkey", $fKey);

        $historyBody = (new FormBody)
            ->addField('since', 0)
            ->addField('mode', 'Messages')
            ->addField("msgCount", 1)
            ->addField("fkey", $fKey);

        $requests = [
            'auth' => (new Request)
                ->setUri($origin . "/ws-auth")
                ->setMethod("POST")
                ->setBody($authBody),
            'history' => (new Request)
                ->setUri("{$origin}/chats/{$room->getId()}/events")
                ->setMethod("POST")
                ->setBody($historyBody),
        ];

        $promise = \Amp\all($this->httpClient->requestMulti($requests));
        /** @var Response[] $responses */
        $responses = \Amp\wait($promise);

        $authInfo = json_try_decode($responses['auth']->getBody(), true);
        $historyInfo = json_try_decode($responses['history']->getBody(), true);
        
        if (!isset($authInfo['url'])) {
            throw new \RuntimeException("WebSocket auth did not return URL");
        }
        if (!isset($historyInfo['time'])) {
            throw new \RuntimeException("Could not get time for WebSocket URL");
        }

        return $authInfo['url'] . '?l=' . $historyInfo['time'];
    }
}
