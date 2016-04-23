<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;
use Amp\Artax\Response;

class Wotd implements Plugin
{
    const COMMAND = 'wotd';

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
        return $message instanceof Command
            && $message->getCommand() === self::COMMAND;
    }

    private function getResult(Message $message): \Generator
    {
        $response = yield from $this->chatClient->request(
            'http://www.dictionary.com/wordoftheday/wotd.rss'
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

        if ($dom->getElementsByTagName('description')->length === 0) {
            return 'I dun goofed';
        }

        preg_match("/([^:]+)/", $dom->getElementsByTagName('description')->item(2)->textContent, $before);
        preg_match("/\:(.*)/", $dom->getElementsByTagName('description')->item(2)->textContent, $after);

        return '**['.$before[0].'](http://www.dictionary.com/browse/'.str_replace(" ", "-", $before[0]).')**' . $after[0];
    }
}
