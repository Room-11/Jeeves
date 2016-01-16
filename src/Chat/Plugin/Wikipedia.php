<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Message\Message;
use Amp\Artax\Client as HttpClient;
use Amp\Artax\Request;
use Amp\Artax\FormBody;

class Wikipedia implements Command
{
    const COMMAND = 'urban';

    private $httpClient;

    private $chatKey;

    public function __construct(HttpClient $httpClient, string $chatKey)
    {
        $this->httpClient = $httpClient;
        $this->chatKey    = $chatKey;
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
        return get_class($message) === 'Room11\Jeeves\Chat\Message\NewMessage'
            && strpos($message->getContent(), '!!wiki') === 0
            && count(explode(' ', trim($message->getContent())) > 1);
    }

    private function getResult(Message $message): \Generator
    {
        $fullCommand = explode(' ', trim($message->getContent()));

        array_shift($fullCommand);

        $promise = $this->httpClient->request('https://en.wikipedia.org/w/api.php?format=json&action=query&titles=' . implode('%20', $fullCommand));

        $response = yield $promise;

        $result = json_decode($response->getBody(), true);
        $firstHit = reset($result['query']['pages']);

        if (isset($firstHit['pageid'])) {
            yield from $this->postResult($message, $firstHit);
        } else {
            yield from $this->postNoResult($message);
        }
    }

    private function postResult(Message $message, array $result): \Generator
    {
        $promise = $this->httpClient->request('http://en.wikipedia.org/w/api.php?action=query&prop=info&pageids=' . $result['pageid'] . '&inprop=url&format=json');

        $response = yield $promise;

        $page     = json_decode($response->getBody(), true);

        $body = (new FormBody)
            ->addField('text', $page['query']['pages'][$result['pageid']]['canonicalurl'])
            ->addField('fkey', $this->chatKey);

        $request = (new Request)
            ->setUri('http://chat.stackoverflow.com/chats/' . $message->getRoomid() . '/messages/new')
            ->setMethod('POST')
            ->setBody($body);

        $promise = $this->httpClient->request($request);

        yield $promise;
    }

    private function postNoResult(Message $message): \Generator
    {
        $body = (new FormBody)
            ->addField('text', sprintf(':%s %s', $message->getId(), 'Sorry I couldn\'t find that page.'))
            ->addField('fkey', $this->chatKey);

        $request = (new Request)
            ->setUri('http://chat.stackoverflow.com/chats/' . $message->getRoomid() . '/messages/new')
            ->setMethod('POST')
            ->setBody($body);

        $promise = $this->httpClient->request($request);

        yield $promise;
    }
}
