<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;

class Wotd implements Plugin
{
    use CommandOnlyPlugin;

    private $chatClient;

    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getResult(Command $command): \Generator
    {
        $response = yield $this->httpClient->request(
            'http://www.dictionary.com/wordoftheday/wotd.rss'
        );

        yield from $this->chatClient->postMessage(
            $command->getRoom(),
            $this->getMessage($response)
        );
    }

    private function getMessage(HttpResponse $response): string
    {
        $internalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($response->getBody());

        libxml_use_internal_errors($internalErrors);

        if ($dom->getElementsByTagName('description')->length === 0) {
            return 'I dun goofed';
        }

        preg_match("/([^:]+)/", $dom->getElementsByTagName('description')->item(2)->textContent, $before);
        preg_match("/\:(.*)/", $dom->getElementsByTagName('description')->item(2)->textContent, $after);

        return '**['.$before[0].'](http://www.dictionary.com/browse/'.str_replace(" ", "-", $before[0]).')**' . $after[0];
    }

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator
    {
        yield from $this->getResult($command);
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getHandledCommands(): array
    {
        return ['wotd'];
    }
}
