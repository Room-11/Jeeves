<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Room11\DOMUtils\domdocument_load_html;

class Tweet extends BasePlugin
{
    const BASE_URI = "https://api.twitter.com/1.1";

    private $chatClient;

    private $admin;

    private $storage;

    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, AdminStorage $admin, KeyValueStore $keyValueStorage) {
        $this->chatClient  = $chatClient;
        $this->admin       = $admin;
        $this->storage = $keyValueStorage;
        $this->httpClient = $httpClient;
    }

    private function getNonce(): string {
        return bin2hex(random_bytes(16));
    }

    private function isMessageValid(string $url): bool {
        return (bool) preg_match('~^http://chat\.stackoverflow\.com/transcript/message/(\d+)(#\d+)?$~', $url);
    }

    // @todo convert URLs to shortened URLs
    // @todo handle twitter's character limit. perhaps we can do some clever replacing when above the limit?
    private function getMessage(Command $command, string $url) {
        preg_match('~^http://chat\.stackoverflow\.com/transcript/message/(\d+)(?:#\d+)?$~', $url, $matches);

        $messageInfo = yield $this->chatClient->getMessageHTML($command->getRoom(), (int) $matches[1]);

        $messageBody = html_entity_decode($messageInfo, ENT_QUOTES);
        $dom = domdocument_load_html($messageBody, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $this->replaceEmphasizeTags($dom);
        $this->replaceStrikeTags($dom);
        $this->replaceImages($dom);
        $this->replaceHrefs($dom);

        return $this->removePings($dom->textContent);
    }

    private function replaceEmphasizeTags(\DOMDocument $dom) {
        $xpath = new \DOMXPath($dom);

        foreach ($xpath->evaluate("//i|//b") as $node) {
            $formattedNode = $dom->createTextNode("*" . $node->textContent . "*");

            $node->parentNode->replaceChild($formattedNode, $node);
        }
    }

    private function replaceStrikeTags(\DOMDocument $dom) {
        foreach ($dom->getElementsByTagName('strike') as $node) {
            $formattedNode = $dom->createTextNode(/** @lang text */ "<strike>{$node->textContent}</strike>");

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

    private function replaceHrefs(\DOMDocument $dom) {
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

    private function removePings(string $text) {
        return preg_replace('/(?:^|\s)(@[^\s]+)(?:$|\s)/', '', $text);
    }

    public function tweet(Command $command) {
        if (!$this->isMessageValid($command->getParameter(0))) {
            return new Success();
        }

        if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
            return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
        }

        $tweetText = yield from $this->getMessage($command, $command->getParameters()[0]);

        if (mb_strlen($tweetText, "UTF-8") > 140) {
            return $this->chatClient->postReply($command, "Boo! The message exceeds the 140 character limit. :-(");
        }

        $keys = ['oauth.consumer_key', 'oauth.access_token', 'oauth.consumer_secret', 'oauth.access_token_secret'];
        $config = [];

        foreach ($keys as $key) {
            if (!yield $this->storage->exists($key, $command->getRoom())) {
                return $this->chatClient->postReply($command, "I'm not currently configured for tweeting :-(");
            }

            $config[$key] = yield $this->storage->get($key, $command->getRoom());
        }

        $oauthParameters = [
            "oauth_consumer_key"     => $config['oauth.consumer_key'],
            "oauth_token"            => $config['oauth.access_token'],
            "oauth_nonce"            => $this->getNonce(),
            "oauth_timestamp"        => (new \DateTimeImmutable)->format("U"),
            "oauth_signature_method" => "HMAC-SHA1",
            "oauth_version"          => "1.0",
            "status"                 => $tweetText,
        ];

        $oauthParameters = array_map("rawurlencode", $oauthParameters);

        asort($oauthParameters);
        ksort($oauthParameters);

        $queryString = urldecode(http_build_query($oauthParameters, '', '&'));

        $baseString = "POST&" . rawurlencode(self::BASE_URI . "/statuses/update.json") . "&" . rawurlencode($queryString);
        $key        = $config['oauth.consumer_secret'] . "&" . $config['oauth.access_token_secret'];
        $signature  = rawurlencode(base64_encode(hash_hmac('sha1', $baseString, $key, true)));

        $oauthParameters["oauth_signature"] = $signature;
        $oauthParameters = array_map(function($value){
            return '"'. $value . '"';
        }, $oauthParameters);

        unset($oauthParameters["status"]);

        asort($oauthParameters);
        ksort($oauthParameters);

        $authorizationHeader = $auth = "OAuth " . urldecode(http_build_query($oauthParameters, '', ', '));

        $request = (new HttpRequest)
            ->setUri(self::BASE_URI . "/statuses/update.json")
            ->setMethod('POST')
            ->setProtocol('1.1')
            ->setBody('status=' . urlencode($tweetText))
            ->setAllHeaders([
                'Authorization' => $authorizationHeader,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ])
        ;

        /** @var HttpResponse $result */
        $result    = yield $this->httpClient->request($request);
        $tweetInfo = json_decode($result->getBody(), true);
        $tweetUri  = 'https://twitter.com/' . $tweetInfo['user']['screen_name'] . '/status/' . $tweetInfo['id_str'];

        return $this->chatClient->postMessage($command, $tweetUri);
    }

    public function getName(): string
    {
        return 'Tweeter';
    }

    public function getDescription(): string
    {
        return 'Tweets chat messages';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Tweet', [$this, 'tweet'], 'tweet')];
    }
}
