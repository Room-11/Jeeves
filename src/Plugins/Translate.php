<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use DaveRandom\AsyncMicrosoftTranslate\Client as TranslationAPIClient;
use DaveRandom\AsyncMicrosoftTranslate\Credentials as TranslationAPICredentials;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client;
use Room11\StackChat\Room\Room as ChatRoom;

class Translate extends BasePlugin
{
    private const ACCESS_TOKEN_LIFETIME = 580; // really 10 minutes but this should avoid us needing to handle expired tokens

    private $chatClient;
    private $apiConsumer;
    private $storage;
    private $apiCredentials;

    private static $supportedLanguages = null;

    public function __construct(
        Client $chatClient,
        TranslationAPIClient $apiConsumer,
        KeyValueStore $storage,
        /* todo: replace with room-specific settings */
        TranslationAPICredentials $apiCredentials
    ) {
        $this->chatClient = $chatClient;
        $this->apiConsumer = $apiConsumer;
        $this->storage = $storage;
        $this->apiCredentials = $apiCredentials;
    }

    private function getAccessTokenForRoom(ChatRoom $room): \Generator
    {
        if (yield $this->storage->exists('access_token', $room)) {
            list($accessToken, $expires) = yield $this->storage->get('access_token', $room);

            if ($expires > time()) {
                return $accessToken;
            }
        }

        $accessToken = yield $this->apiConsumer->getAccessToken($this->apiCredentials);

        yield $this->storage->set('access_token', [$accessToken, time() + self::ACCESS_TOKEN_LIFETIME], $room);

        return $accessToken;
    }

    private function getTranslation(ChatRoom $room, string $text, string $toLanguage, string $fromLanguage = null)
    {
        $accessToken = yield from $this->getAccessTokenForRoom($room);

        if ($fromLanguage === null) {
            $fromLanguage = yield $this->apiConsumer->detectLanguage($accessToken, $text);
        }

        $translation = yield $this->apiConsumer->getTranslation($accessToken, $text, $fromLanguage, $toLanguage);

        return sprintf('%s (translated from %s)', $translation, self::$supportedLanguages[$fromLanguage]);
    }

    private function getTextFromArguments(ChatRoom $room, array $args)
    {
        if (count($args) > 1 || !preg_match('#/message/([0-9]+)#', $args[0], $match)) {
            return implode(' ', $args);
        }

        return yield $this->chatClient->getMessageText($room, (int)$match[1]);
    }

    /**
     * @param string $language
     * @return string|null
     */
    private function getLanguageCode(string $language)
    {
        if (isset(self::$supportedLanguages[$language])) {
            return $language;
        }

        $languageLower = strtolower($language);

        foreach (self::$supportedLanguages as $code => $name) {
            if (strtolower($code) === $languageLower || strtolower($name) === $languageLower) {
                return $code;
            }
        }

        return null;
    }

    private function postSupportedLanguagesList(Command $command): Promise
    {
        $message = "The languages I speak are:";

        foreach (self::$supportedLanguages as $code => $name) {
            $message .= "\n{$name} ({$code})";
        }

        return $this->chatClient->postMessage($command, $message);
    }

    public function magic(Command $command)
    {
        return yield from $this->toLanguage($command, $command->getCommandName());
    }

    public function toEnglish(Command $command)
    {
        return yield from $this->toLanguage($command, 'en');
    }

    public function toLanguage(Command $command, string $toLanguage = null)
    {
        $params = $command->getParameters();

        if ($params[0] === 'list') {
            return $this->postSupportedLanguagesList($command);
        }

        $toLanguage = $toLanguage ?? array_shift($params);
        if (null === $toLanguageCode = $this->getLanguageCode($toLanguage ?? array_shift($params))) {
            return $this->chatClient->postReply($command, 'Sorry, I don\'t speak ' . $toLanguage);
        }

        $fromLanguageCode = null;
        if (preg_match('#^--from(?:=(.+))?$#', $params[0] ?? '', $match)) {
            array_shift($params);

            $fromLanguage = empty($match[1]) ? array_shift($params) : $match[1];

            if (null === $fromLanguageCode = $this->getLanguageCode($fromLanguage)) {
                return $this->chatClient->postReply($command, 'Sorry, I don\'t speak ' . $fromLanguage);
            }
        }

        if (count($params) < 1) {
            return $this->chatClient->postReply($command, 'The empty string is the same in every language');
        }

        $text = yield from $this->getTextFromArguments($command->getRoom(), $params);
        $translation = yield from $this->getTranslation($command->getRoom(), $text, $toLanguageCode, $fromLanguageCode);

        return $this->chatClient->postMessage($command, $translation);
    }

    public function getDescription(): string
    {
        return 'Translates text another language';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint('Translate', [$this, 'toLanguage'], 'translate', 'Translates text to the specified language'),
            new PluginCommandEndpoint('ToEnglish', [$this, 'toEnglish'], 'en', 'Translates text to English'),
            new PluginCommandEndpoint('Magic', [$this, 'magic'], null, 'Translates text to the language indicated by the mapped command'),
        ];
    }

    public function enableForRoom(ChatRoom $room, bool $persist)
    {
        if (self::$supportedLanguages === []) {
            return;
        }

        self::$supportedLanguages = [];

        $accessToken = yield from $this->getAccessTokenForRoom($room);
        self::$supportedLanguages = yield $this->apiConsumer->getSupportedLanguages($accessToken);
    }
}
