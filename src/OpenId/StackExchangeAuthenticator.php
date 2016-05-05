<?php declare(strict_types = 1);

namespace Room11\Jeeves\OpenId;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Artax\Uri;
use function Room11\Jeeves\domdocument_load_html;

class StackExchangeAuthenticator implements Authenticator
{
    private $credentials;
    private $httpClient;

    public function __construct(Credentials $credentials, HttpClient $httpClient) {
        $this->credentials = $credentials;
        $this->httpClient = $httpClient;
    }

    public function logIn(string $url): \Generator
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($url);

        $doc = domdocument_load_html($response->getBody());
        $xpath = new \DOMXPath($doc);

        $fkey = $this->getFKey($xpath);
        $submitURL = $this->getSubmitURL($xpath, $response->getRequest()->getUri());

        $body = (new FormBody)
            ->addField("email", (string) $this->credentials->getEmailAddress())
            ->addField("password", (string) $this->credentials->getPassword())
            ->addField("fkey", $fkey)
            ->addField("ssrc", "")
            ->addField("oauth_version", "")
            ->addField("oauth_server", "")
            ->addField("openid_username", "")
            ->addField("openid_identifier", "");

        $request = (new HttpRequest)
            ->setUri($submitURL)
            ->setMethod("POST")
            ->setBody($body);

        return yield $this->httpClient->request($request);
    }

    private function getSubmitURL(\DOMXPath $xpath, string $baseURL): string
    {
        /** @var \DOMElement $node */

        $nodes = $xpath->query("//form[@id='login-form']");
        if ($nodes->length < 1) {
            throw new \RuntimeException('Could not find login form');
        }

        $node = $nodes->item(0);
        return (string) (new Uri($baseURL))->resolve($node->getAttribute('action'));
    }

    private function getFKey(\DOMXPath $xpath): string
    {
        /** @var \DOMElement $node */

        $nodes = $xpath->query("//input[@name='fkey']");
        if ($nodes->length < 1) {
            throw new \RuntimeException('Could not find fkey for login form');
        }

        $node = $nodes->item(0);
        return $node->getAttribute('value');
    }
}
