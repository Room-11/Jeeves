<?php

namespace Room11\Jeeves\Chat\Room;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Room11\OpenId\Authenticator as OpenIdAuthenticator;
use Room11\OpenId\Credentials;
use function Amp\all;
use function Room11\Jeeves\domdocument_load_html;
use Room11\OpenId\UriFactory;

class Authenticator
{
    private $httpClient;
    private $roomFactory;
    private $authenticator;
    private $credentialManager;
    /**
     * @var UriFactory
     */
    private $uriFactory;

    public function __construct(
        HttpClient $httpClient,
        RoomFactory $roomFactory,
        OpenIdAuthenticator $authenticator,
        UriFactory $uriFactory,
        CredentialManager $credentialManager
    )
    {
        $this->httpClient = $httpClient;
        $this->roomFactory = $roomFactory;
        $this->authenticator = $authenticator;
        $this->uriFactory = $uriFactory;
        $this->credentialManager = $credentialManager;
    }

    public function logIn(Identifier $identifier): \Generator
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($identifier->getEndpointURL(Endpoint::UI));

        $doc = domdocument_load_html($response->getBody());
        $xpath = new \DOMXPath($doc);

        $mainSiteURL = $this->getMainSiteUrl($xpath);

        if (!$this->isLoggedInMainSite($doc)) {
            $xpath = yield from $this->logInMainSite($doc, $this->getOpenIdCredentials($mainSiteURL));
        }

        $fkey = $this->getFKey($xpath);

        $webSocketURL = yield from $this->getWebSocketUri($identifier, $fkey);

        return $this->roomFactory->build($identifier, $fkey, $webSocketURL, $mainSiteURL);
    }

    private function logInMainSite(\DOMDocument $doc, Credentials $credentials): \Generator
    {
        $url = $this->getLogInURL(new \DOMXPath($doc));

        /** @var HttpResponse $response */
        $response = yield from $this->authenticator->logIn($url, $credentials);

        $doc = domdocument_load_html($response->getBody());
        if (!$this->isLoggedInMainSite($doc)) {
            throw new \RuntimeException('Still not logged in'); //todo
        }

        return new \DOMXPath($doc);
    }

    private function getOpenIdCredentials(string $url): Credentials
    {
        return $this->credentialManager->getCredentialsForDomain($this->uriFactory->build($url)->getHost());
    }

    private function isLoggedInMainSite(\DOMDocument $doc)
    {
        return $doc->getElementById('input') !== null;
    }

    private function getLogInURL(\DOMXPath $xpath): string
    {
        /** @var \DOMElement $node */

        $nodes = $xpath->query("//div[@id='bubble']/a[text()='logged in']");
        if ($nodes->length < 1) {
            throw new \RuntimeException('Could not get login URL node'); //todo
        }

        $node = $nodes->item(0);
        return $node->getAttribute('href');
    }

    private function getMainSiteUrl(\DOMXPath $xpath): string
    {
        /** @var \DOMElement $node */

        $nodes = $xpath->query("//td[@id='footer-logo']/a");
        if ($nodes->length < 1) {
            throw new \RuntimeException('Could not find URL for the main site for this chat room');
        }

        $node = $nodes->item(0);
        return $node->getAttribute('href');
    }

    private function getFKey(\DOMXPath $xpath): string
    {
        /** @var \DOMElement $node */

        $nodes = $xpath->query("//input[@name='fkey']");
        if ($nodes->length < 1) {
            throw new \RuntimeException('Could not find fkey for chat room');
        }

        $node = $nodes->item(0);
        return $node->getAttribute('value');
    }

    private function getWebSocketUri(Identifier $identifier, string $fKey): \Generator
    {
        $authBody = (new FormBody)
            ->addField("roomid", $identifier->getId())
            ->addField("fkey", $fKey);

        $historyBody = (new FormBody)
            ->addField('since', 0)
            ->addField('mode', 'Messages')
            ->addField("msgCount", 1)
            ->addField("fkey", $fKey);

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
        $responses = yield all($this->httpClient->requestMulti($requests));

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
