<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\CommandOnly;
use Room11\Jeeves\Chat\PluginCommandEndpoint;

class Wikipedia implements Plugin
{
    use CommandOnly;

    private $chatClient;

    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function postResult(Command $command, array $result): \Generator
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            'http://en.wikipedia.org/w/api.php?action=query&prop=info&pageids=' . $result['pageid'] . '&inprop=url&format=json'
        );

        $page = json_decode($response->getBody(), true);

        yield from $this->chatClient->postMessage($command->getRoom(), $page['query']['pages'][$result['pageid']]['canonicalurl']);
    }

    private function postNoResult(Command $command): \Generator
    {
        yield from $this->chatClient->postReply($command, 'Sorry I couldn\'t find that page.');
    }

    public function search(Command $command): \Generator
    {
        if (!$command->hasParameters()) {
            return;
        }

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            'https://en.wikipedia.org/w/api.php?format=json&action=query&titles=' . rawurlencode(implode('%20', $command->getParameters()))
        );

        $result   = json_decode($response->getBody(), true);
        $firstHit = reset($result['query']['pages']);

        if (isset($firstHit['pageid'])) {
            yield from $this->postResult($command, $firstHit);
        } else {
            yield from $this->postNoResult($command);
        }
    }

    public function getName(): string
    {
        return 'Wikipedia';
    }

    public function getDescription(): string
    {
        return 'Looks up wikipedia entries and posts onebox links';
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
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'wiki')];
    }
}
