<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use PeeHaa\AsyncTwitter\Api\Client\Client as TwitterClient;
use PeeHaa\AsyncTwitter\Api\Client\ClientFactory as TwitterClientFactory;
use PeeHaa\AsyncTwitter\Api\Client\Exception\RequestFailed as TwitterRequestFailedException;
use PeeHaa\AsyncTwitter\Api\Request\Media\Response\UploadResponse;
use PeeHaa\AsyncTwitter\Api\Request\Media\Upload;
use PeeHaa\AsyncTwitter\Api\Request\Status\Retweet as RetweetRequest;
use PeeHaa\AsyncTwitter\Api\Request\Status\Update as UpdateRequest;
use PeeHaa\AsyncTwitter\Credentials\AccessTokenFactory as TwitterAccessTokenFactory;
use Room11\DOMUtils\LibXMLFatalErrorException;
use function Room11\DOMUtils\xpath_html_class;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\Exception;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Client\MessageIDNotFoundException;
use Room11\StackChat\Client\MessageResolver as ChatMessageResolver;
use Room11\StackChat\Entities\MainSiteUser;
use Room11\StackChat\Room\Room as ChatRoom;
use function Room11\DOMUtils\domdocument_load_html;

class NotConfiguredException extends Exception {}
class TweetIDNotFoundException extends Exception {}
class TweetLengthLimitExceededException extends Exception {}
class TextProcessingFailedException extends Exception {}

class Tweet extends BasePlugin
{
    private const MAX_TWEET_LENGTH = 280;

    private $chatClient;
    private $admin;
    private $keyValueStore;
    private $apiClientFactory;
    private $accessTokenFactory;
    private $httpClient;
    private $messageResolver;

    /**
     * @var TwitterClient[]
     */
    private $clients = [];

    public function __construct(
        ChatClient $chatClient,
        HttpClient $httpClient,
        AdminStorage $admin,
        KeyValueStore $keyValueStore,
        TwitterClientFactory $apiClientFactory,
        TwitterAccessTokenFactory $accessTokenFactory,
        ChatMessageResolver $messageResolver
    ) {
        $this->chatClient         = $chatClient;
        $this->admin              = $admin;
        $this->keyValueStore      = $keyValueStore;
        $this->apiClientFactory   = $apiClientFactory;
        $this->accessTokenFactory = $accessTokenFactory;
        $this->httpClient         = $httpClient;
        $this->messageResolver    = $messageResolver;
    }

    private function getRawMessage(ChatRoom $room, string $link)
    {
        $messageID = $this->messageResolver->resolveMessageIDFromPermalink($link);

        $messageInfo = yield $this->chatClient->getMessageHTML($room, $messageID);

        $messageBody = html_entity_decode($messageInfo, ENT_QUOTES);

        return domdocument_load_html($messageBody, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    }

    private function isRetweet(\DOMXPath $xpath): bool
    {
        return $xpath->query("//*[" . xpath_html_class('ob-tweet') . "]")->length > 0;
    }

    private function getTweetIdFromMessage(\DOMXPath $xpath): int
    {
        /** @var \DOMElement $element */
        foreach ($xpath->document->getElementsByTagName('a') as $element) {
            if (!preg_match('~https?://twitter.com/[^/]+/status/(\d+)~', $element->getAttribute('href'), $matches)) {
                continue;
            }

            return (int)$matches[1];
        }

        throw new TweetIDNotFoundException("ID not found");
    }

    private function replaceImages(\DOMXPath $xpath)
    {
        $files = [];

        /** @var \DOMElement $element */
        foreach ($xpath->document->getElementsByTagName('img') as $element) {
            $target = $element->getAttribute('src');

            if (substr($target, 0, 2) === '//') {
                $target = 'https:' . $target;
            }

            /** @var HttpResponse $response */
            $request = (new HttpRequest)
                ->setMethod('GET')
                ->setUri($target)
                ->setHeader('Connection', 'close');

            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($request);
            $tmpFilePath = \Room11\Jeeves\DATA_BASE_DIR . '/' . uniqid('twitter-media-', true);
            yield \Amp\File\put($tmpFilePath, $response->getBody());

            $element->parentNode->parentNode->removeChild($element->parentNode);

            $files[] = $tmpFilePath;
        }

        return $files;
    }

    private function replaceAnchors(\DOMXPath $xpath)
    {
        /** @var \DOMElement $element */
        foreach ($xpath->document->getElementsByTagName('a') as $element) {
            $linkText = "";

            if ($element->getAttribute('href') !== $element->textContent) {
                $linkText = " (" . $element->textContent . ")";
            }

            $formattedNode = $xpath->document->createTextNode($element->getAttribute('href') . $linkText);

            $element->parentNode->replaceChild($formattedNode, $element);
        }
    }

    private function replacePings(ChatRoom $room, string $text)
    {
        static $pingExpr = '/@([^\s]+)(?=$|\s)/';

        if (!preg_match_all($pingExpr, $text, $matches)) {
            return $text;
        }

        $pingableIDs = yield $this->chatClient->getPingableUserIDs($room, ...$matches[1]);
        /** @var int $ids because PHP storm is dumb */
        $ids = array_values($pingableIDs);
        $users = yield $this->chatClient->getMainSiteUsers($room, ...$ids);

        /** @var MainSiteUser[] $pingableUsers */
        $pingableUsers = [];
        foreach ($pingableIDs as $name => $id) {
            $pingableUsers[$name] = $users[$id];
        }

        return preg_replace_callback($pingExpr, function($match) use($pingableUsers) {
            $handle = isset($pingableUsers[$match[1]])
                ? $pingableUsers[$match[1]]->getTwitterHandle()
                : null;

            return $handle !== null ? '@' . $handle : $match[1];
        }, $text);
    }

    private function fixBrokenImgurUrls(string $text)
    {
        return preg_replace('~((?!https?:)//i(?:\.stack)?\.imgur\.com/)~', 'https:\1', $text);
    }

    private function getClientForRoom(ChatRoom $room)
    {
        $ident = $room->getIdentString();

        if (isset($this->clients[$ident])) {
            return $this->clients[$ident];
        }

        $keys = ['oauth.access_token', 'oauth.access_token_secret'];
        $config = [];

        foreach ($keys as $key) {
            if (!yield $this->keyValueStore->exists($key, $room)) {
                throw new NotConfiguredException('Missing config key: ' . $key);
            }

            $config[$key] = yield $this->keyValueStore->get($key, $room);
        }

        $accessToken = $this->accessTokenFactory->create($config['oauth.access_token'], $config['oauth.access_token_secret']);
        $this->clients[$ident] = $this->apiClientFactory->create($accessToken);

        return $this->clients[$ident];
    }

    private function buildRetweetRequest(\DOMXPath $xpath): RetweetRequest
    {
        return new RetweetRequest($this->getTweetIdFromMessage($xpath));
    }

    private function buildUpdateRequest(ChatRoom $room, \DOMXPath $xpath)
    {
        $files = yield from $this->replaceImages($xpath);
        $this->replaceAnchors($xpath);

        $text = trim(yield from $this->replacePings($room, $xpath->document->textContent));
        $text = $this->fixBrokenImgurUrls($text);
        $text = \Normalizer::normalize(trim($text), \Normalizer::FORM_C);

        if ($text === false) {
            throw new TextProcessingFailedException;
        }

        if (mb_strlen($text, 'UTF-8') > self::MAX_TWEET_LENGTH) {
            throw new TweetLengthLimitExceededException;
        }

        $result = new UpdateRequest($text);

        if (count($files) > 0) {
            $mediaIds = yield from $this->uploadMediaFiles($room, $files);
            $result->setMediaIds(...$mediaIds);
        }

        return $result;
    }

    private function uploadMediaFiles(ChatRoom $room, array $files)
    {
        /** @var TwitterClient $client */
        $client = yield from $this->getClientForRoom($room);
        $ids = [];

        foreach ($files as $file) {
            /** @var UploadResponse $result */
            $result = yield $client->request((new Upload)->setFilePath($file));
            $ids[] = $result->getMediaId();

            yield \Amp\File\unlink($file);
        }

        return $ids;
    }

    private function buildTwitterRequest(ChatRoom $room, \DOMXPath $xpath)
    {
        return $this->isRetweet($xpath)
            ? $this->buildRetweetRequest($xpath)
            : yield from $this->buildUpdateRequest($room, $xpath);
    }

    public function tweet(Command $command)
    {
        $room = $command->getRoom();

        if (!yield $this->admin->isAdmin($room, $command->getUserId())) {
            return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
        }

        try {
            /** @var TwitterClient $client */
            $client = yield from $this->getClientForRoom($room); // do this first to make sure it's worth going further

            $doc = yield from $this->getRawMessage($room, $command->getParameter(0));
            $xpath = new \DOMXPath($doc);

            $request = yield from $this->buildTwitterRequest($room, $xpath);

            $result = yield $client->request($request);

            $tweetURL = sprintf('https://twitter.com/%s/status/%s', $result['user']['screen_name'], $result['id_str']);

            return $this->chatClient->postMessage($command, $tweetURL);
        } catch (NotConfiguredException $e) {
            return $this->chatClient->postReply($command, "I'm not currently configured for tweeting :-(");
        } catch (LibXMLFatalErrorException $e) {
            return $this->chatClient->postReply($command, 'Totally failed to parse the chat message :-(');
        } catch (MessageIDNotFoundException $e) {
            return $this->chatClient->postReply($command, 'I need a chat message link to tweet');
        } catch (TweetIDNotFoundException $e) {
            return $this->chatClient->postReply($command, "That looks like a retweet but I can't find the tweet ID :-S");
        } catch (TextProcessingFailedException $e) {
            return $this->chatClient->postReply($command, "Processing the message text failed :-S");
        } catch (TweetLengthLimitExceededException $e) {
            return $this->chatClient->postReply($command, "Boo! The message exceeds the 140 character limit. :-(");
        } catch (TwitterRequestFailedException $e) {
            return $this->chatClient->postReply($command, 'Posting to Twitter failed :-( ' . $e->getMessage());
        }
    }

    public function getName(): string
    {
        return 'BetterTweet';
    }

    public function getDescription(): string
    {
        return 'Tweets chat messages just like !!tweet only better (WIP)';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('BetterTweet', [$this, 'tweet'], 'tweet2')];
    }
}
