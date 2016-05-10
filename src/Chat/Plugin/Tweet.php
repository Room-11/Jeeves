<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\CommandOnly;
use Room11\Jeeves\Chat\PluginCommandEndpoint;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Twitter\Credentials;
use function Room11\DOMUtils\domdocument_load_html;

class Tweet implements Plugin
{
    use CommandOnly;

    const BASE_URI = "https://api.twitter.com/1.1";

    private $chatClient;

    private $admin;

    private $credentials;

    private $twitterConfig = [];

    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, AdminStorage $admin, Credentials $credentials) {
        $this->chatClient  = $chatClient;
        $this->admin       = $admin;
        $this->credentials = $credentials;
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
    private function getMessage(Command $command, string $url): \Generator {
        preg_match('~^http://chat\.stackoverflow\.com/transcript/message/(\d+)(?:#\d+)?$~', $url, $matches);

        /** @var HttpResponse $messageInfo */
        $messageInfo = yield from $this->chatClient->getMessage($command->getRoom(), (int) $matches[1]);

        $messageBody = html_entity_decode($messageInfo->getBody(), ENT_QUOTES);
        $dom = domdocument_load_html($messageBody, 'UTF-8', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $this->replaceEmphasizeTags($dom);
        $this->replaceStrikeTags($dom);
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
            $formattedNode = $dom->createTextNode("<strike>" . $node->textContent . "</strike>");

            $node->parentNode->replaceChild($formattedNode, $node);
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

    // @todo move the oauth request making to a separate class to prevent code duplication and 110 responsibilities
    private function updateConfigWhenNeeded(): \Generator {
        if (array_key_exists("expiration", $this->twitterConfig) && $this->twitterConfig["expiration"] > new \DateTimeImmutable()) {
            return;
        }

        $oauthParameters = [
            "oauth_consumer_key"     => $this->credentials->getConsumerKey(),
            "oauth_token"            => $this->credentials->getAccessToken(),
            "oauth_nonce"            => $this->getNonce(),
            "oauth_timestamp"        => (new \DateTimeImmutable())->format("U"),
            "oauth_signature_method" => "HMAC-SHA1",
            "oauth_version"          => "1.0",
        ];

        $oauthParameters = array_map("rawurlencode", $oauthParameters);

        asort($oauthParameters);
        ksort($oauthParameters);

        $queryString = urldecode(http_build_query($oauthParameters, '', '&'));

        $baseString = "GET&" . rawurlencode(self::BASE_URI . "/help/configuration.json") . "&" . rawurlencode($queryString);
        $key        = $this->credentials->getConsumerSecret() . "&" . $this->credentials->getAccessTokenSecret();
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
            ->setUri(self::BASE_URI . "/help/configuration.json")
            ->setMethod('GET')
            ->setProtocol('1.1')
            ->setAllHeaders([
                'Authorization' => $authorizationHeader,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ])
        ;

        /** @var HttpResponse $result */
        $result = yield $this->httpClient->request($request);

        $this->twitterConfig = json_decode($result->getBody(), true);
        $this->twitterConfig["expiration"] = (new \DateTimeImmutable())->add(new \DateInterval("P1D"));

        var_dump($this->twitterConfig);
    }

    public function tweet(Command $command): \Generator {
        if (!$this->isMessageValid($command->getParameter(0))) {
            return;
        }

        if (!yield from $this->admin->isAdmin($command->getUserId())) {
            yield from $this->chatClient->postReply(
                $command, "I'm sorry Dave, I'm afraid I can't do that"
            );

            return;
        }

        yield from $this->updateConfigWhenNeeded();

        $tweetText = yield from $this->getMessage($command, $command->getParameters()[0]);

        if (mb_strlen($tweetText, "UTF-8") > 140) {
            yield from $this->chatClient->postReply($command, "Boo! The message exceeds the 140 character limit. :-(");

            return;
        }

        $oauthParameters = [
            "oauth_consumer_key"     => $this->credentials->getConsumerKey(),
            "oauth_token"            => $this->credentials->getAccessToken(),
            "oauth_nonce"            => $this->getNonce(),
            "oauth_timestamp"        => (new \DateTimeImmutable())->format("U"),
            "oauth_signature_method" => "HMAC-SHA1",
            "oauth_version"          => "1.0",
            "status"                 => $tweetText,
        ];

        $oauthParameters = array_map("rawurlencode", $oauthParameters);

        asort($oauthParameters);
        ksort($oauthParameters);

        $queryString = urldecode(http_build_query($oauthParameters, '', '&'));

        $baseString = "POST&" . rawurlencode(self::BASE_URI . "/statuses/update.json") . "&" . rawurlencode($queryString);
        $key        = $this->credentials->getConsumerSecret() . "&" . $this->credentials->getAccessTokenSecret();
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

        yield from $this->chatClient->postReply($command, $tweetUri);
    }

    public function getName(): string
    {
        return 'Tweeter';
    }

    public function getDescription(): string
    {
        return 'Tweets chat messages';
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
        return [new PluginCommandEndpoint('Tweet', [$this, 'tweet'], 'tweet')];
    }
}
