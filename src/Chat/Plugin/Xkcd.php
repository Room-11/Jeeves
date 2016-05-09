<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use function Room11\DOMUtils\domdocument_load_html;

class Xkcd implements Plugin {
    use CommandOnlyPlugin;

    const NOT_FOUND_COMIC = 'https://xkcd.com/1334/';

    private $chatClient;

    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getResult(Command $command): \Generator {
        $uri = "https://www.google.com/search?q=site:xkcd.com+intitle%3a%22xkcd%3a+%22+" . urlencode(implode(' ', $command->getParameters()));

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($uri);

        if ($response->getStatus() !== 200) {
            yield from $this->chatClient->postMessage(
                $command->getRoom(),
                "Useless error message here so debugging this is harder than needed."
            );

            return;
        }

        $dom = domdocument_load_html($response->getBody());
        $nodes = (new \DOMXPath($dom))
            ->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' g ')]/h3/a");

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            if (preg_match('~^/url\?q=(https://xkcd\.com/\d+/)~', $node->getAttribute('href'), $matches)) {
                yield from $this->chatClient->postMessage($command->getRoom(), $matches[1]);

                return;
            }
        }

        yield from $this->chatClient->postMessage($command->getRoom(), self::NOT_FOUND_COMIC);
    }

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator
    {
        if (!$command->getParameters()) {
            return;
        }

        yield from $this->getResult($command);
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getHandledCommands(): array
    {
        return ['xkcd'];
    }
}
