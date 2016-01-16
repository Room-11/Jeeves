<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\Xhr as ChatClient;
use Room11\Jeeves\Chat\Command\Message;
use Amp\Artax\Response;

class Docs implements Plugin
{
    const COMMAND = 'docs';

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
            'http://php.net/manual-lookup.php?scope=quickref&pattern=' . rawurlencode(implode(' ', $message->getParameters()))
        );

        if ($response->getPreviousResponse() !== null) {
            yield from $this->chatClient->postMessage(
                $this->getMessageFromMatch($response)
            );
        } else {
            yield from $this->chatClient->postMessage(
                yield from $this->getMessageFromSearch($response)
            );
        }
    }

    private function getMessageFromMatch(Response $response): string
    {
        $internalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($response->getBody());

        libxml_use_internal_errors($internalErrors);

        $xpath = new \DOMXPath($dom);

        return sprintf(
            '[ [%s](%s) ] %s',
            $dom->getElementsByTagName('h1')->item(0)->textContent,
            $response->getRequest()->getUri(),
            $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dc-title ')]")->item(0)->textContent
        );
    }

    private function getMessageFromSearch(Response $response): \Generator
    {
        $internalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($response->getBody());

        libxml_use_internal_errors($internalErrors);

        $xpath = new \DOMXPath($dom);

        $firstResult = $dom->getElementById('quickref_functions')->getElementsByTagName('li')->item(0);

        $response = yield from $this->chatClient->request(
            'https://php.net' . $firstResult->getElementsByTagName('a')->item(0)->getAttribute('href')
        );

        return $this->getMessageFromMatch($response);
    }

    private function getMessageFromSearch2(Response $response): string
    {
        $internalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($response->getBody());

        libxml_use_internal_errors($internalErrors);

        $xpath = new \DOMXPath($dom);

        $firstResult = $dom->getElementById('quickref_functions')->getElementsByTagName('li')->item(0);

        var_dump($firstResult);

        return sprintf(
            '[ [%s](%s) ] %s',
            $firstResult->textContent,
            'https://php.net' . $firstResult->getElementsByTagName('a')->item(0)->getAttribute('href'),
            'foo'
        );
    }
}
