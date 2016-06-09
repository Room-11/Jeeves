<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\PluginCommandEndpoint;
use Room11\Jeeves\Plugin;
use Room11\Jeeves\Plugin\Traits\AutoName;
use Room11\Jeeves\Plugin\Traits\CommandOnly;
use Room11\Jeeves\Plugin\Traits\Helpless;

class Wikipedia implements Plugin
{
    use CommandOnly, AutoName, Helpless;

    private $chatClient;

    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    public function search(Command $command): \Generator
    {
        if (!$command->hasParameters()) {
            return new Success();
        }

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            'https://en.wikipedia.org/w/api.php?format=json&action=query&titles=' . rawurlencode(implode('%20', $command->getParameters()))
        );

        $result   = json_decode($response->getBody(), true);
        $firstHit = reset($result['query']['pages']);

        if (!isset($firstHit['pageid'])) {
            return $this->chatClient->postReply($command, 'Sorry I couldn\'t find that page.');
        }

        $response = yield $this->httpClient->request(
            'http://en.wikipedia.org/w/api.php?action=query&prop=info&pageids=' . $result['pageid'] . '&inprop=url&format=json'
        );

        $page = json_decode($response->getBody(), true);

        return $this->chatClient->postMessage($command->getRoom(), $page['query']['pages'][$result['pageid']]['canonicalurl']);
    }

    public function getDescription(): string
    {
        return 'Looks up wikipedia entries and posts onebox links';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'wiki')];
    }
}
