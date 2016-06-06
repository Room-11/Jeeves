<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\CommandOnly;
use Room11\Jeeves\Chat\PluginCommandEndpoint;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\MicrosoftTranslationAPI\Consumer as TranslationAPIConsumer;
use Room11\Jeeves\MicrosoftTranslationAPI\Credentials as TranslationAPICredentials;

class Translate implements Plugin
{
    use CommandOnly;

    const ACCESS_TOKEN_LIFETIME = 580; // really 10 minutes but this should avoid us needing to handle expired tokens

    private $chatClient;
    private $apiConsumer;
    private $storage;
    private $apiCredentials;

    public function __construct(
        ChatClient $chatClient,
        TranslationAPIConsumer $apiConsumer,
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

        $accessToken = yield $this->apiConsumer->getAccessToken(
            $this->apiCredentials->getClientId(),
            $this->apiCredentials->getClientSecret()
        );

        yield $this->storage->set('access_token', [$accessToken, time() + self::ACCESS_TOKEN_LIFETIME], $room);

        return $accessToken;
    }

    private function getTranslation(ChatRoom $room, string $text, string $toLanguage): \Generator
    {
        $accessToken = yield from $this->getAccessTokenForRoom($room);
        $fromLanguage = yield $this->apiConsumer->detectLanguage($accessToken, $text);

        return yield $this->apiConsumer->getTranslation($accessToken, $text, $fromLanguage, $toLanguage);
    }

    private function getTextFromArguments(ChatRoom $room, array $args): \Generator
    {
        if (count($args) > 1 || !preg_match('#/message/([0-9]+)#', $args[0], $match)) {
            return implode(' ', $args);
        }

        return yield $this->chatClient->getMessageText($room, (int)$match[1]);
    }

    public function toDanish(Command $command): \Generator
    {
        return yield from $this->toLanguage($command, 'da');
    }

    public function toDutch(Command $command): \Generator
    {
        return yield from $this->toLanguage($command, 'nl');
    }

    public function toEnglish(Command $command): \Generator
    {
        return yield from $this->toLanguage($command, 'en');
    }

    public function toFrench(Command $command): \Generator
    {
        return yield from $this->toLanguage($command, 'fr');
    }

    public function toGerman(Command $command): \Generator
    {
        return yield from $this->toLanguage($command, 'de');
    }

    public function toItalian(Command $command): \Generator
    {
        return yield from $this->toLanguage($command, 'it');
    }

    public function toKlingon(Command $command): \Generator
    {
        return yield from $this->toLanguage($command, 'tlh');
    }

    public function toPortuguese(Command $command): \Generator
    {
        return yield from $this->toLanguage($command, 'pt');
    }

    public function toSpanish(Command $command): \Generator
    {
        return yield from $this->toLanguage($command, 'es');
    }

    public function toLanguage(Command $command, string $language = null): \Generator
    {
        $params = $command->getParameters();

        if ($language === null) {
            $language = array_shift($params);
        }

        if (count($params) < 1) {
            return $this->chatClient->postReply($command, 'The empty string is the same in every language');
        }

        $text = yield from $this->getTextFromArguments($command->getRoom(), $params);
        $translation = yield from $this->getTranslation($command->getRoom(), $text, $language);

        return $this->chatClient->postMessage($command->getRoom(), $translation);
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
            new PluginCommandEndpoint('Translate',   [$this, 'toLanguage'],    'translate', 'Translates text to the specified language'),
            new PluginCommandEndpoint('ToDanish',     [$this, 'toDanish'],     'da', 'Translates text to Danish'),
            new PluginCommandEndpoint('ToDutch',      [$this, 'toDutch'],      'nl', 'Translates text to Dutch'),
            new PluginCommandEndpoint('ToEnglish',    [$this, 'ToEnglish'],    'en', 'Translates text to English'),
            new PluginCommandEndpoint('ToFrench',     [$this, 'toFrench'],     'fr', 'Translates text to French'),
            new PluginCommandEndpoint('ToGerman',     [$this, 'toGerman'],     'de', 'Translates text to German'),
            new PluginCommandEndpoint('ToItalian',    [$this, 'toItalian'],    'it', 'Translates text to Italian'),
            new PluginCommandEndpoint('ToKlingon',    [$this, 'toKlingon'],    'klingon', 'Translates text to Klingon'),
            new PluginCommandEndpoint('ToPortuguese', [$this, 'toPortuguese'], 'pt', 'Translates text to Portuguese'),
            new PluginCommandEndpoint('ToSpanish',    [$this, 'toSpanish'],    'es', 'Translates text to Spanish'),
        ];
    }
}
