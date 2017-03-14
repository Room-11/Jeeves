<?php

namespace Room11\Jeeves\External\MicrosoftTranslationAPI;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use function Amp\resolve;

class Consumer
{
    private const AUTH_URL = 'https://datamarket.accesscontrol.windows.net/v2/OAuth2-13/';
    private const BASE_URL = 'http://api.microsofttranslator.com';
    private const SERVICE_URL = self::BASE_URL . '/V2/Http.svc';

    private $httpClient;

    private function callApiMethod(HttpRequest $request, callable $transformCallback = null)
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($request);

        $doc = new \DOMDocument;
        $doc->loadXML((string)$response->getBody());

        return $transformCallback ? $transformCallback($doc) : $doc;
    }

    private function callApiGetMethod(string $accessToken, string $method, array $params = null, callable $transformCallback = null): Promise
    {
        $url = self::SERVICE_URL . '/' . $method . ($params ? '?' . http_build_query($params) : '');

        $request = (new HttpRequest)
            ->setUri($url)
            ->setHeader('Authorization', 'Bearer ' . $accessToken);

        return resolve($this->callApiMethod($request, $transformCallback));
    }

    private function callApiPostMethod(string $accessToken, string $method, \DOMDocument $document, callable $transformCallback = null): Promise
    {
        $url = self::SERVICE_URL . '/' . $method;

        $request = (new HttpRequest)
            ->setUri($url)
            ->setMethod('POST')
            ->setHeader('Authorization', 'Bearer ' . $accessToken)
            ->setHeader('Content-Type', 'text/xml')
            ->setBody($document->saveXML());

        return resolve($this->callApiMethod($request, $transformCallback));
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

    public function getSupportedLanguages(string $accessToken, string $locale = 'en'): Promise
    {
        return resolve(function() use($accessToken, $locale) {
            /** @var \DOMDocument $doc */
            $doc = yield $this->callApiGetMethod($accessToken, 'GetLanguagesForTranslate');

            $codes = [];
            foreach ($doc->getElementsByTagName('string') as $string) {
                $codes[] = $string->textContent;
            }

            $doc = yield $this->callApiPostMethod($accessToken, 'GetLanguageNames?locale=' . urlencode($locale), $doc);

            $languages = [];
            foreach ($doc->getElementsByTagName('string') as $i => $string) {
                $languages[$codes[$i]] = $string->textContent;
            }

            asort($languages);
            return $languages;
        });
    }

    public function detectLanguage(string $accessToken, string $text): Promise
    {
        $args = ['text' => $text];

        return $this->callApiGetMethod($accessToken, 'Detect', $args, function(\DOMDocument $doc) {
            return $doc->textContent;
        });
    }

    public function getTranslation(string $accessToken, string $text, string $from, string $to): Promise
    {
        $args = [
            'text' => $text,
            'from' => $from,
            'to' => $to,
        ];

        return $this->callApiGetMethod($accessToken, 'Translate', $args, function(\DOMDocument $doc) {
            return $doc->textContent;
        });
    }
}
