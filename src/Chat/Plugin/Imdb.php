<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient as ChatClient;
use Room11\Jeeves\Chat\Command\Message;
use Amp\Artax\Response;

class Imdb implements Plugin
{
    const COMMAND = 'imdb';

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
            'http://www.imdb.com/xml/find?xml=1&nr=1&tt=on&q=' . rawurlencode(implode(' ', $message->getParameters()))
        );

        yield from $this->chatClient->postMessage(
            $this->getMessage($response)
        );
    }

    private function getMessage(Response $response): string
    {
        $internalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($response->getBody());

        libxml_use_internal_errors($internalErrors);

        if ($dom->getElementsByTagName('resultset')->length === 0) {
            return 'I cannot find that title.';
        }

        $result = $dom->getElementsByTagName('imdbentity')->item(0);

        return sprintf(
            '[ [%s](%s) ] %s',
            $result->firstChild->wholeText,
            'http://www.imdb.com/title/' . $result->getAttribute('id'),
            $result->getElementsByTagName('description')->item(0)->textContent
        );
    }
}
