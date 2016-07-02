<?php  declare(strict_types=1);
namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Room11\DOMUtils\domdocument_load_html;

class Xkcd extends BasePlugin
{
    const NOT_FOUND_COMIC = 'https://xkcd.com/1334/';

    private $chatClient;
    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    public function search(Command $command): \Generator {
        if (!$command->hasParameters()) {
            return new Success();
        }

        $uri = "https://www.google.com/search?q=site:xkcd.com+intitle%3a%22xkcd%3a+%22+" . urlencode(implode(' ', $command->getParameters()));

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($uri);

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage(
                $command->getRoom(),
                "Useless error message here so debugging this is harder than needed."
            );
        }

        $dom = domdocument_load_html($response->getBody());
        $nodes = (new \DOMXPath($dom))
            ->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' g ')]/h3/a");

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            if (preg_match('~^/url\?q=(https://xkcd\.com/\d+/)~', $node->getAttribute('href'), $matches)) {
                return $this->chatClient->postMessage($command->getRoom(), $matches[1]);
            }
        }

        if (preg_match('/^(\d+)$/', trim($command->getParameters(), $matches)) !== 1) {
            return $this->chatClient->postMessage($command->getRoom(), self::NOT_FOUND_COMIC);
        }

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request('https://xkcd.com/' . $matches[1]);

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command->getRoom(), self::NOT_FOUND_COMIC);
        }

        return $this->chatClient->postMessage($command->getRoom(), 'https://xkcd.com/' . $matches[1]);
    }

    public function getName(): string
    {
        return 'xkcd';
    }

    public function getDescription(): string
    {
        return 'Searches for relevant comics from xkcd and posts them as a onebox';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'xkcd')];
    }
}
