<?php declare(strict_types = 1);

namespace Room11\Jeeves\OpenId;

use Amp\Artax\HttpClient;
use Amp\Artax\FormBody;
use Amp\Artax\Request;
use Amp\Artax\Response;
use Room11\Jeeves\Fkey\Retriever as FkeyRetriever;

class StackOverflowLogin {
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

    public function logIn() {
        $body = (new FormBody)
            ->addField("email", (string) $this->credentials->getEmailAddress())
            ->addField("password", (string) $this->credentials->getPassword())
            ->addField("fkey", (string) $this->fkeyRetriever->get(self::FKEY_URL))
            ->addField("ssrc", "")
            ->addField("oauth_version", "")
            ->addField("oauth_server", "")
            ->addField("openid_username", "")
            ->addField("openid_identifier", "");

        $request = (new Request)
            ->setUri(self::LOGIN_URL)
            ->setMethod("POST")
            ->setBody($body);

        $promise = $this->httpClient->request($request);

        /** @var Response $response */
        $response = \Amp\wait($promise);

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
