<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient as ChatClient;
use Room11\Jeeves\Chat\Command\Message;

class Wikipedia implements Plugin
{
    const COMMAND = 'wiki';

    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function handle(Message $message): \Generator
    {
        if (!$this->validMessage($message)) {
            return;
        }

        yield from $this->getResult($message);
    }

    private function validMessage(Message $message): bool
    {
        return get_class($message) === 'Room11\Jeeves\Chat\Command\Command'
        && $message->getCommand() === self::COMMAND
        && $message->getParameters();
    }

    private function getResult(Message $message): \Generator
    {
        $response = yield from $this->chatClient->request(
            'https://en.wikipedia.org/w/api.php?format=json&action=query&titles=' . rawurlencode(implode('%20', $message->getParameters()))
        );

        $result   = json_decode($response->getBody(), true);
        $firstHit = reset($result['query']['pages']);

        if (isset($firstHit['pageid'])) {
            yield from $this->postResult($firstHit);
        } else {
            yield from $this->postNoResult($message);
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

    private function postNoResult(Message $message): \Generator
    {
        yield from $this->chatClient->postMessage(
            sprintf(':%s %s', $message->getOrigin(), 'Sorry I couldn\'t find that page.')
        );
    }
}
