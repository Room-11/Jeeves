<?php

namespace Room11\Jeeves\Chat\Command;

use Room11\Jeeves\Chat\Message\Message;
use Amp\Artax\Client as HttpClient;
use Amp\Artax\Request;
use Amp\Artax\FormBody;

class Version implements Command
{
    const COMMAND = 'version';

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

        yield from $this->getVersion($message);
    }

    private function validMessage(Message $message): bool
    {
        return get_class($message) === 'Room11\Jeeves\Chat\Message\NewMessage' && trim($message->getContent()) === '!!version';
    }

    private function getVersion(Message $message): \Generator
    {
        $body = (new FormBody)
            ->addField('text', 'v0.0.1')
            ->addField('fkey', $this->chatKey)
        ;

        $request = (new Request)
            ->setUri('http://chat.stackoverflow.com/chats/' . $message->getRoomid() . '/messages/new')
            ->setMethod('POST')
            ->setBody($body)
        ;

        $promise = $this->httpClient->request($request);

        yield $promise;
    }
}
