<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Command;

use Room11\Jeeves\Chat\Message\Message;
use Amp\Artax\Client as HttpClient;
use Amp\Artax\Request;
use Amp\Artax\FormBody;

class Urban implements Command
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
            && strpos($message->getContent(), '!!urban') === 0
            && count(explode(' ', trim($message->getContent())) > 1);
    }

    private function getResult(Message $message): \Generator
    {
        $fullCommand = explode(' ', trim($message->getContent()));

        array_shift($fullCommand);

        $promise = $this->httpClient->request('http://api.urbandictionary.com/v0/define?term=' . implode('%20', $fullCommand));

        $response = yield $promise;

        $result = json_decode($response->getBody(), true);

        yield from $this->postResult($message, $result);
    }

    private function postResult(Message $message, array $result)
    {
        $body = (new FormBody)
            ->addField('text', sprintf('[ [%s](%s) ] %s', $result['list'][0]['word'], $result['list'][0]['permalink'], str_replace('\r\n', "\r\n", $result['list'][0]['definition'])))
            ->addField('fkey', $this->chatKey);

        $request = (new Request)
            ->setUri('http://chat.stackoverflow.com/chats/' . $message->getRoomid() . '/messages/new')
            ->setMethod('POST')
            ->setBody($body);

        $promise = $this->httpClient->request($request);

        yield $promise;
    }
}
