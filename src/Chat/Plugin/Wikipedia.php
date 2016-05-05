<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;

class Wikipedia implements Plugin
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
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            'https://en.wikipedia.org/w/api.php?format=json&action=query&titles=' . rawurlencode(implode('%20', $command->getParameters()))
        );

        $result   = json_decode($response->getBody(), true);
        $firstHit = reset($result['query']['pages']);

        if (isset($firstHit['pageid'])) {
            yield from $this->postResult($firstHit);
        } else {
            yield from $this->postNoResult($command);
        }
    }

    private function postResult(array $result): \Generator
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            'http://en.wikipedia.org/w/api.php?action=query&prop=info&pageids=' . $result['pageid'] . '&inprop=url&format=json'
        );

        $page = json_decode($response->getBody(), true);

        yield from $this->chatClient->postMessage($page['query']['pages'][$result['pageid']]['canonicalurl']);
    }

    private function postNoResult(Command $command): \Generator
    {
        yield from $this->chatClient->postReply($command, 'Sorry I couldn\'t find that page.');
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
        return ['wiki'];
    }
}
