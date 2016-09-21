<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\PluginCommandEndpoint;
use PeeHaa\AsyncTwitter\Api\Client as ApiClient;
use PeeHaa\AsyncTwitter\Api\Status\Update;
use function Room11\DOMUtils\domdocument_load_html;

class BetterTweet extends BasePlugin
{
    private $chatClient;

    private $admin;

    private $apiClient;

    private $httpClient;

    public function __construct(
        ChatClient $chatClient,
        HttpClient $httpClient,
        AdminStorage $admin,
        ApiClient $apiClient
    ) {
        $this->chatClient = $chatClient;
        $this->admin      = $admin;
        $this->apiClient  = $apiClient;
        $this->httpClient = $httpClient;
    }

    private function isMessageValid(string $url): bool
    {
        return (bool) preg_match('~^http://chat\.stackoverflow\.com/transcript/message/(\d+)(#\d+)?$~', $url);
    }

    private function getMessage(Command $command, string $url): \Generator
    {
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

    private function replaceEmphasizeTags(\DOMDocument $dom)
    {
        $xpath = new \DOMXPath($dom);

        foreach ($xpath->evaluate("//i|//b") as $node) {
            $formattedNode = $dom->createTextNode("*" . $node->textContent . "*");

            $node->parentNode->replaceChild($formattedNode, $node);
        }
    }

    private function replaceStrikeTags(\DOMDocument $dom)
    {
        foreach ($dom->getElementsByTagName('strike') as $node) {
            $formattedNode = $dom->createTextNode("<strike>" . $node->textContent . "</strike>");

            $node->parentNode->replaceChild($formattedNode, $node);
        }
    }

    private function replaceImages(\DOMDocument $dom)
    {
        foreach ($dom->getElementsByTagName('img') as $node)
        {
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

    private function removePings(string $text): string
    {
        return preg_replace('/(?:^|\s)(@[^\s]+)(?:$|\s)/', '', $text);
    }

    public function tweet(Command $command): Promise
    {
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

        /** @var HttpResponse $result */
        $result    = yield (new Update($this->apiClient, $tweetText))->post();
        $tweetInfo = json_decode($result->getBody(), true);
        $tweetUri  = 'https://twitter.com/' . $tweetInfo['user']['screen_name'] . '/status/' . $tweetInfo['id_str'];

        return $this->chatClient->postMessage($command->getRoom(), $tweetUri);
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
