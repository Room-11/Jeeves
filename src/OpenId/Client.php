<?php declare(strict_types = 1);

namespace Room11\Jeeves\OpenId;

use Amp\Artax\Client as HttpClient;
use Amp\Artax\FormBody;
use Amp\Artax\Request;
use Room11\Jeeves\Chat\Room\Room;
use Room11\Jeeves\Fkey\Retriever as FkeyRetriever;

class Client {
    private $credentials;
    private $httpClient;
    private $fkeyRetriever;

    public function __construct(Credentials $credentials, HttpClient $httpClient, FkeyRetriever $fkeyRetriever) {
        $this->credentials = $credentials;
        $this->httpClient = $httpClient;
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

        $body = (new FormBody)
            ->addField("roomid", $room->getId())
            ->addField("fkey", (string) $this->fkeyRetriever->get($origin . "/rooms/" . $room->getId()));

        $request = (new Request)
            ->setUri($origin . "/ws-auth")
            ->setMethod("POST")
            ->setBody($body);

        $promise = $this->httpClient->request($request);
        $response = \Amp\wait($promise);

        return json_decode($response->getBody(), true)["url"];
    }
}
