<?php

namespace Room11\Jeeves\Chat\Room;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Fkey\FKey;
use Room11\Jeeves\Fkey\Retriever as FKeyRetriever;

class Connector
{
    private $httpClient;
    private $fkeyRetriever;
    private $roomFactory;

    public function __construct(HttpClient $httpClient, FKeyRetriever $fkeyRetriever, RoomFactory $roomFactory)
    {
        $this->httpClient = $httpClient;
        $this->fkeyRetriever = $fkeyRetriever;
        $this->roomFactory = $roomFactory;
    }

    public function connect(RoomIdentifier $identifier): \Generator
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($identifier->getEndpointURL(Endpoint::UI));

        $internalErrors = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML($response->getBody());
        libxml_use_internal_errors($internalErrors);

        $fkey = $this->fkeyRetriever->getFromDOMDocument($doc);
        $mainSiteURL = $this->getMainSiteURL($doc);

        $webSocketURL = yield from $this->getWebSocketUri($identifier, $fkey);

        return $this->roomFactory->build($identifier, $fkey, $mainSiteURL, $webSocketURL);
    }

    public function getMainSiteURL(\DOMDocument $doc): string
    {
        $siteRefNodes = (new \DOMXPath($doc))->query("//td[@id='footer-logo']/a");
        if ($siteRefNodes->length < 1) {
            throw new \RuntimeException('Could not find URL for the main site for this chat room');
        }

        /** @var \DOMElement $siteRefNode */
        $siteRefNode = $siteRefNodes->item(0);
        return $siteRefNode->getAttribute('href');
    }

    public function getWebSocketUri(RoomIdentifier $identifier, FKey $fKey): \Generator
    {
        $authBody = (new FormBody)
            ->addField("roomid", $identifier->getId())
            ->addField("fkey", (string) $fKey);

        $historyBody = (new FormBody)
            ->addField('since', 0)
            ->addField('mode', 'Messages')
            ->addField("msgCount", 1)
            ->addField("fkey", (string) $fKey);

        $requests = [
            'auth' => (new HttpRequest)
                ->setUri($identifier->getEndpointURL(Endpoint::WEBSOCKET_AUTH))
                ->setMethod("POST")
                ->setBody($authBody),
            'history' => (new HttpRequest)
                ->setUri($identifier->getEndpointURL(Endpoint::EVENT_HISTORY))
                ->setMethod("POST")
                ->setBody($historyBody),
        ];

        /** @var HttpResponse[] $responses */
        $responses = yield $this->httpClient->requestMulti($requests);

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
