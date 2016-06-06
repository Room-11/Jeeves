<?php

namespace Room11\Jeeves\MicrosoftTranslationAPI;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use function Amp\resolve;

class Consumer
{
    const AUTH_URL      = 'https://datamarket.accesscontrol.windows.net/v2/OAuth2-13/';
    const BASE_URL      = 'http://api.microsofttranslator.com';
    const DETECT_URL    = self::BASE_URL . '/V2/Http.svc/Detect?text=%s';
    const TRANSLATE_URL = self::BASE_URL . '/V2/Http.svc/Translate?text=%s&from=%s&to=%s';

    private $httpClient;

    private function sendRequestAndGetXMLTextContent(HttpRequest $request): \Generator
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($request);

        $doc = new \DOMDocument;
        $doc->loadXML((string)$response->getBody());

        return $doc->textContent;
    }

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getAccessToken(string $clientID, string $clientSecret): Promise
    {
        $body = (new FormBody)
            ->addField('grant_type', 'client_credentials')
            ->addField('scope', self::BASE_URL)
            ->addField('client_id', $clientID)
            ->addField('client_secret', $clientSecret);

        $request = (new HttpRequest)
            ->setMethod('POST')
            ->setUri(self::AUTH_URL)
            ->setBody($body);

        return resolve(function() use($request) {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($request);

            $decoded = json_try_decode($response->getBody());
            if (!empty($decoded->error)){
                throw new \RuntimeException($decoded->error_description);
            }

            return $decoded->access_token;
        });
    }

    public function detectLanguage(string $accessToken, string $text): Promise
    {
        $url = sprintf(self::DETECT_URL, urlencode($text));

        $request = (new HttpRequest)
            ->setUri($url)
            ->setHeader('Authorization', 'Bearer ' . $accessToken);

        return resolve($this->sendRequestAndGetXMLTextContent($request));
    }

    public function getTranslation(string $accessToken, string $text, string $from, string $to): Promise
    {
        $url = sprintf(self::TRANSLATE_URL, urlencode($text), urlencode($from), urlencode($to));

        $request = (new HttpRequest)
            ->setUri($url)
            ->setHeader('Authorization', 'Bearer ' . $accessToken);

        return resolve($this->sendRequestAndGetXMLTextContent($request));
    }
}
