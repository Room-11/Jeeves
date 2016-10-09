<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use PeeHaa\AsyncTwitter\Api\Client as TwitterClient;
use PeeHaa\AsyncTwitter\Api\Client\ClientFactory as TwitterClientFactory;
use PeeHaa\AsyncTwitter\Api\Client\Exception\RequestFailed as TwitterRequestFailedException;
use PeeHaa\AsyncTwitter\Api\Request\Status\Retweet as RetweetRequest;
use PeeHaa\AsyncTwitter\Api\Request\Status\Update as UpdateRequest;
use PeeHaa\AsyncTwitter\Credentials\AccessTokenFactory as TwitterAccessTokenFactory;
use Room11\DOMUtils\LibXMLFatalErrorException;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\MessageIDNotFoundException;
use Room11\Jeeves\Chat\Client\MessageResolver as ChatMessageResolver;
use Room11\Jeeves\Chat\Entities\MainSiteUser;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Exception;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Room11\DOMUtils\domdocument_load_html;

class NotConfiguredException extends Exception {}
class TweetIDNotFoundException extends Exception {}
class TweetLengthLimitExceededException extends Exception {}
class TextProcessingFailedException extends Exception {}

class BetterTweet extends BasePlugin
{
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

    private function isRetweet(\DOMDocument $dom): bool
    {
        return (bool)(new \DOMXPath($dom))
            ->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' ob-tweet ')]")
            ->length;
    }

    private function getTweetIdFromMessage(\DOMDocument $dom): int
    {
        /** @var \DOMElement $node */
        foreach ($dom->getElementsByTagName('a') as $node) {
            if (!preg_match('~https?://twitter.com/[^/]+/status/(\d+)~', $node->getAttribute('href'), $matches)) {
                continue;
            }

            return (int)$matches[1];
        }

        throw new TweetIDNotFoundException("ID not found");
    }

    private function replaceEmphasizeTags(\DOMDocument $dom)
    {
        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query("//i|//b") as $node) {
            $formattedNode = $dom->createTextNode("*" . $node->textContent . "*");

            $node->parentNode->replaceChild($formattedNode, $node);
        }
    }

    private function replaceStrikeTags(\DOMDocument $dom)
    {
        foreach ($dom->getElementsByTagName('strike') as $node) {
            /** @noinspection HtmlDeprecatedTag */
            $formattedNode = $dom->createTextNode("<strike>" . $node->textContent . "</strike>");

            $node->parentNode->replaceChild($formattedNode, $node);
        }
    }

    private function replaceImages(\DOMDocument $dom)
    {
        foreach ($dom->getElementsByTagName('img') as $node) {
            /** @var \DOMElement $node */
            $formattedNode = $dom->createTextNode($node->getAttribute('src'));

            $node->parentNode->parentNode->replaceChild($formattedNode, $node->parentNode);
        }
    }

    private function replaceHrefs(\DOMDocument $dom)
    {
        foreach ($dom->getElementsByTagName('a') as $node) {
            /** @var \DOMElement $node */
            $linkText = "";

            if ($node->getAttribute('href') !== $node->textContent) {
                $linkText = " (" . $node->textContent . ")";
            }

            $formattedNode = $dom->createTextNode($node->getAttribute('href') . $linkText);

            $node->parentNode->replaceChild($formattedNode, $node);
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

    private function getClientForRoom(ChatRoom $room)
    {
        if (isset($this->clients[$room->getIdentifier()->getIdentString()])) {
            return $this->clients[$room->getIdentifier()->getIdentString()];
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
        $this->clients[$room->getIdentifier()->getIdentString()] = $this->apiClientFactory->create($accessToken);

        return $this->clients[$room->getIdentifier()->getIdentString()];
    }

    private function buildRetweetRequest(\DOMDocument $dom): RetweetRequest
    {
        return new RetweetRequest($this->getTweetIdFromMessage($dom));
    }

    private function buildUpdateRequest(ChatRoom $room, \DOMDocument $dom)
    {
        $this->replaceEmphasizeTags($dom);
        $this->replaceStrikeTags($dom);
        $this->replaceImages($dom);
        $this->replaceHrefs($dom);

        $text = yield from $this->replacePings($room, $dom->textContent);
        $text = \Normalizer::normalize(trim($text), \Normalizer::FORM_C);

        if ($text === false) {
            throw new TextProcessingFailedException;
        }

        if (mb_strlen($text, "UTF-8") > 140) {
            throw new TweetLengthLimitExceededException;
        }

        return new UpdateRequest($text);
    }

    private function buildTwitterRequest(ChatRoom $room, \DOMDocument $dom)
    {
        return $this->isRetweet($dom)
            ? $this->buildRetweetRequest($dom)
            : yield from $this->buildUpdateRequest($room, $dom);
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

            $message = yield from $this->getRawMessage($room, $command->getParameter(0));

            $request = yield from $this->buildTwitterRequest($room, $message);

            $result = yield $client->request($request);

            $tweetURL = sprintf('https://twitter.com/%s/status/%s', $result['user']['screen_name'], $result['id_str']);

            return $this->chatClient->postMessage($room, $tweetURL);
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
