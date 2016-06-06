<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\CommandOnly;
use Room11\Jeeves\Chat\PluginCommandEndpoint;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;

class Translate implements Plugin
{
    use CommandOnly;

    const AUTH_URL      = 'https://datamarket.accesscontrol.windows.net/v2/OAuth2-13/';
    const BASE_URL      = 'http://api.microsofttranslator.com';
    const DETECT_URL    = self::BASE_URL . '/V2/Http.svc/Detect?text=%s';
    const TRANSLATE_URL = self::BASE_URL . '/V2/Http.svc/Translate?text=%s&from=%s&to=%s';

    private $chatClient;
    private $httpClient;
    private $storage;

    private function getAccessToken(string $clientID, string $clientSecret): \Generator
    {
        $body = (new FormBody)
            ->addField("grant_type", 'client_credentials')
            ->addField("scope", self::BASE_URL)
            ->addField("client_id", $clientID)
            ->addField("client_secret", $clientSecret);

        $request = (new HttpRequest)
            ->setMethod('POST')
            ->setUri(self::AUTH_URL)
            ->setBody($body);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($request);

        $decoded = json_try_decode($response->getBody());
        if (!empty($decoded->error)){
            throw new \RuntimeException($decoded->error_description);
        }

        return $decoded->access_token;
    }

    private function detectLanguage(string $text, string $accessToken): \Generator
    {
        $url = sprintf(self::DETECT_URL, urlencode($text));

        $request = (new HttpRequest)
            ->setUri($url)
            ->setHeader('Authorization', 'Bearer ' . $accessToken);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($request);

        $doc = new \DOMDocument;
        $doc->loadXML((string)$response->getBody());

        return $doc->textContent;
    }

    private function getTranslation(string $text, string $from, string $to, string $accessToken): \Generator
    {
        $url = sprintf(self::TRANSLATE_URL, urlencode($text), urlencode($from), urlencode($to));

        $request = (new HttpRequest)
            ->setUri($url)
            ->setHeader('Authorization', 'Bearer ' . $accessToken);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($request);

        $doc = new \DOMDocument;
        $doc->loadXML((string)$response->getBody());

        return $doc->textContent;
    }

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, KeyValueStore $storage)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->storage = $storage;
    }

    private function getCachedAccessTokenForRoom(ChatRoom $room): \Generator
    {
        if (!yield $this->storage->exists('access_token', $room)) {
            return null;
        }

        list($accessToken, $expires) = yield $this->storage->get('access_token', $room);

        return $expires > time()
            ? $accessToken
            : null;
    }

    public function toEnglish(Command $command): \Generator
    {
        if (!$command->hasParameters()) {
            return $this->chatClient->postReply($command, 'The empty string is the same in every language');
        }

        $text = implode(' ', $command->getParameters());

        $accessToken = yield from $this->getCachedAccessTokenForRoom($command->getRoom());

        if ($accessToken === null) {
            $accessToken = yield from $this->getAccessToken('JeevesTranslate', '');
            yield $this->storage->set('access_token', $accessToken, $command->getRoom());
        }

        $fromLanguage = yield from $this->detectLanguage($text, $accessToken);
        $translation = yield from $this->getTranslation($text, $fromLanguage, 'en', $accessToken);

        return $this->chatClient->postMessage($command->getRoom(), $translation);
    }

    public function toLanguage(Command $command)
    {

    }

    public function getName(): string
    {
        return 'Translate';
    }

    public function getDescription(): string
    {
        return 'Translates text another language';
    }

    public function getHelpText(array $args): string
    {
        // TODO: Implement getHelpText() method.
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint('ToLanguage', [$this, 'toLanguage'], 'translate', 'Translates text to the specified language'),
            new PluginCommandEndpoint('ToEnglish', [$this, 'toEnglish'], 'en', 'Translates text to English'),
        ];
    }
}
