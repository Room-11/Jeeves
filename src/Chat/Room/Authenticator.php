<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\Room;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\OpenId\Authenticator as OpenIdAuthenticator;
use Room11\OpenId\Credentials;
use Room11\OpenId\UriFactory;
use function Amp\all;
use function Room11\DOMUtils\domdocument_load_html;

class Authenticator
{
    private $httpClient;
    private $sessionInfoFactory;
    private $authenticator;
    private $credentialManager;
    private $uriFactory;
    private $chatClient;

    public function __construct(
        HttpClient $httpClient,
        ChatClient $chatClient,
        SessionInfoFactory $sessionInfoFactory,
        OpenIdAuthenticator $authenticator,
        UriFactory $uriFactory,
        CredentialManager $credentialManager
    )
    {
        $this->httpClient = $httpClient;
        $this->chatClient = $chatClient;
        $this->sessionInfoFactory = $sessionInfoFactory;
        $this->authenticator = $authenticator;
        $this->uriFactory = $uriFactory;
        $this->credentialManager = $credentialManager;
    }

    public function getRoomSessionInfo(Identifier $identifier): \Generator
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($identifier->getEndpointURL(Endpoint::CHATROOM_UI));

        $doc = domdocument_load_html($response->getBody());
        $xpath = new \DOMXPath($doc);

        $mainSiteURL = $this->getMainSiteUrl($xpath);

        if (!$this->isLoggedInMainSite($doc)) {
            $xpath = yield from $this->logInMainSite($doc, $this->getOpenIdCredentials($mainSiteURL));
        }

        $fkey = $this->getFKey($xpath);
        $user = yield from $this->getUser($identifier, $xpath);

        $webSocketURL = yield from $this->getWebSocketUri($identifier, $fkey);

        return $this->sessionInfoFactory->build($user, $fkey, $mainSiteURL, $webSocketURL);
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

    private function getUser(Identifier $identifier, \DOMXPath $xpath): \Generator
    {
        /** @var \DOMElement $node */

        $nodes = $xpath->query("//div[@id='active-user']");
        if ($nodes->length < 1) {
            throw new \RuntimeException('Could not find user ID for chat room: no active-user div');
        }

        $node = $nodes->item(0);
        if (!preg_match('#\buser-([0-9]+)\b#', $node->getAttribute('class'), $match)) {
            throw new \RuntimeException('Could not find user ID for chat room: no user ID class');
        }

        $user = yield $this->chatClient->getChatUsers($identifier, (int)$match[1]);

        return $user[0];
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
                ->setUri($identifier->getEndpointURL(Endpoint::CHATROOM_WEBSOCKET_AUTH))
                ->setMethod("POST")
                ->setBody($authBody),
            'history' => (new HttpRequest)
                ->setUri($identifier->getEndpointURL(Endpoint::CHATROOM_EVENT_HISTORY))
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
