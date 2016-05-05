<?php declare(strict_types = 1);

namespace Room11\Jeeves\OpenId;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Fkey\Retriever as FkeyRetriever;

class StackOverflowLogin
{
    const FKEY_URL = "https://stackoverflow.com/users/login?returnurl=%2f";
    const LOGIN_URL = "https://stackoverflow.com/users/login?returnurl=%2f";

    private $credentials;
    private $httpClient;
    private $fkeyRetriever;

    public function __construct(Credentials $credentials, HttpClient $httpClient, FkeyRetriever $fkeyRetriever) {
        $this->credentials = $credentials;
        $this->httpClient = $httpClient;
        $this->fkeyRetriever = $fkeyRetriever;
    }

    public function logIn(): \Generator {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(self::FKEY_URL);
        $fkey = $this->fkeyRetriever->getFromHtml($response->getBody());

        $body = (new FormBody)
            ->addField("email", (string) $this->credentials->getEmailAddress())
            ->addField("password", (string) $this->credentials->getPassword())
            ->addField("fkey", (string) $fkey)
            ->addField("ssrc", "")
            ->addField("oauth_version", "")
            ->addField("oauth_server", "")
            ->addField("openid_username", "")
            ->addField("openid_identifier", "");

        $request = (new HttpRequest)
            ->setUri(self::LOGIN_URL)
            ->setMethod("POST")
            ->setBody($body);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($request);

        if (!$this->verifyLogin($response->getBody())) {
            throw new FailedAuthenticationException();
        }
    }

    public function verifyLogin(string $html): bool {
        $dom = new \DOMDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors($internalErrors);

        return !strpos($dom->getElementsByTagName("title")->item(0)->textContent, "Log In");
    }
}
