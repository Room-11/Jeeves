<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\PostedMessage;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\CommandOnly;
use Room11\Jeeves\Chat\PluginCommandEndpoint;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use function Room11\DOMUtils\domdocument_load_html;

class RFC implements Plugin
{
    use CommandOnly;

    private $chatClient;
    private $httpClient;
    private $pluginData;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, KeyValueStore $pluginData)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->pluginData = $pluginData;
    }

    public function search(Command $command): \Generator {
        $uri = "https://wiki.php.net/rfc";

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($uri);

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command->getRoom(), "Nope, we can't have nice things.");
        }

        $dom = domdocument_load_html($response->getBody());

        $list = $dom->getElementById("in_voting_phase")->nextSibling->nextSibling->getElementsByTagName("ul")->item(0);
        $rfcsInVoting = [];

        foreach ($list->childNodes as $node) {
            if ($node instanceof \DOMText) {
                continue;
            }

            /** @var \DOMElement $node */
            /** @var \DOMElement $href */
            $href = $node->getElementsByTagName("a")->item(0);

            $rfcsInVoting[] = sprintf(
                "[%s](%s)",
                $href->textContent,
                \Sabre\Uri\resolve($uri, $href->getAttribute("href"))
            );
        }

        if (empty($rfcsInVoting)) {
            return $this->chatClient->postMessage($command->getRoom(), "Sorry, but we can't have nice things.");
        }

        /** @var PostedMessage $postedMessage */
        $postedMessage = yield $this->chatClient->postMessage(
            $command->getRoom(),
            sprintf(
                "[tag:rfc-vote] %s",
                implode(" | ", $rfcsInVoting)
            )
        );

        $pinnedMessages = yield $this->chatClient->getPinnedMessages($command->getRoom());
        $lastPinId = (yield $this->pluginData->exists('lastpinid', $command->getRoom()))
            ? yield $this->pluginData->get('lastpinid', $command->getRoom())
            : -1;

        if (in_array($lastPinId, $pinnedMessages)) {
            yield $this->chatClient->unstarMessage($lastPinId, $command->getRoom());
        }

        yield $this->pluginData->set('lastpinid', $postedMessage->getMessageId(), $command->getRoom());
        return $this->chatClient->pinOrUnpinMessage($postedMessage->getMessageId(), $command->getRoom());
    }

    public function getName(): string
    {
        return 'RFC.PHP';
    }

    public function getDescription(): string
    {
        return 'Displays the PHP RFCs which are currently in the voting phase';
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
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'rfcs')];
    }
}
