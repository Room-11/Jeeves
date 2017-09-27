<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;
use function Room11\DOMUtils\domdocument_load_html;

class Wotd extends BasePlugin
{
    private const API_URL = 'http://www.dictionary.com/wordoftheday/wotd.rss';

    private $chatClient;
    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getMessage(HttpResponse $response): string
    {
        $dom = domdocument_load_html($response->getBody());

        if ($dom->getElementsByTagName('definition-box')->length === 0) {
            return 'I dun goofed';
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' definition-box ')]");

        $word       = $nodes->item(0)->getElementsByTagName('strong')->item(0)->textContent;
        $definition = $nodes->item(0)->getElementsByTagName('li')->item(0)->textContent;

        return '**['.$word.'](http://www.dictionary.com/browse/'.str_replace(" ", "-", $word).')**' . $definition;
    }

    public function fetch(Command $command)
    {
        $response = yield $this->httpClient->request(self::API_URL);

        return $this->chatClient->postMessage($command, $this->getMessage($response));
    }

    public function getDescription(): string
    {
        return 'Gets the Word Of The Day from dictionary.com';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Fetch', [$this, 'fetch'], 'wotd')];
    }
}
