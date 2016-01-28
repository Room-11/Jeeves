<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
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
        return $message instanceof Command
            && $message->getCommand() === self::COMMAND
            && $message->getParameters();
    }

    private function getResult(Message $message): \Generator
    {
        $pattern = str_replace('::', '.', implode(' ', $message->getParameters()));

        if (substr($pattern, 0, 6) === "mysql_") {
            yield from $this->chatClient->postMessage(
                $this->getMysqlMessage()
            );

            return;
        }

        $url = 'http://php.net/manual-lookup.php?scope=quickref&pattern=' . rawurlencode($pattern);

        $response = yield from $this->chatClient->request($url);

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

    private function getMysqlMessage(): string {
        // See https://gist.github.com/MadaraUchiha/3881905
        return "[**Please, don't use `mysql_*` functions in new code**](http://bit.ly/phpmsql). "
             . "They are no longer maintained [and are officially deprecated](http://j.mp/XqV7Lp). "
             . "See the [**red box**](http://j.mp/Te9zIL)? Learn about [*prepared statements*](http://j.mp/T9hLWi) instead, "
             . "and use [PDO](http://php.net/pdo) or [MySQLi](http://php.net/mysqli) - "
             . "[this article](http://j.mp/QEx8IB) will help you decide which. If you choose PDO, "
             . "[here is a good tutorial](http://j.mp/PoWehJ).";
    }

    private function getMessageFromMatch(Response $response): string
    {
        $internalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($response->getBody());

        libxml_use_internal_errors($internalErrors);

        $xpath = new \DOMXPath($dom);

        return sprintf(
            '[ [`%s`](%s) ] %s',
            $dom->getElementsByTagName('h1')->item(0)->textContent,
            $response->getRequest()->getUri(),
            trim($xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dc-title ')]")->item(0)->textContent)
        );
    }

    private function getMessageFromSearch(Response $response): \Generator
    {
        $internalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($response->getBody());

        libxml_use_internal_errors($internalErrors);

        $firstResult = $dom->getElementById('quickref_functions')->getElementsByTagName('li')->item(0);

        $response = yield from $this->chatClient->request(
            'https://php.net' . $firstResult->getElementsByTagName('a')->item(0)->getAttribute('href')
        );

        return $this->getMessageFromMatch($response);
    }
}
