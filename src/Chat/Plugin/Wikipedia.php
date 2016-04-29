<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;

class Wikipedia implements Plugin
{
    use CommandOnlyPlugin;

    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    private function getResult(Command $command): \Generator
    {
        $response = yield from $this->chatClient->request(
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
        $response = yield from $this->chatClient->request(
            'http://en.wikipedia.org/w/api.php?action=query&prop=info&pageids=' . $result['pageid'] . '&inprop=url&format=json'
        );

        $page = json_decode($response->getBody(), true);

        yield from $this->chatClient->postMessage($page['query']['pages'][$result['pageid']]['canonicalurl']);
    }

    private function postNoResult(Command $command): \Generator
    {
        yield from $this->chatClient->postReply($command->getMessage(), 'Sorry I couldn\'t find that page.');
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
