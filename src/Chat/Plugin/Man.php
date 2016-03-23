<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;

class Man implements Plugin
{
    const COMMAND = 'man';

    private $chatClient;

    public function __construct(ChatClient $chatClient) {
        $this->chatClient = $chatClient;
    }

    public function handle(Message $message): \Generator {
        if (!$this->validMessage($message)) {
            return;
        }

        /** @var Command $message */
        yield from $this->getResult($message);
    }

    private function validMessage(Message $message): bool {
        return $message instanceof Command
        && $message->getCommand() === self::COMMAND
        && $message->getParameters();
    }

    private function getResult(Command $message): \Generator {
        $response = yield from $this->chatClient->request(
            "https://man.freebsd.org/" . rawurlencode(implode("%20", $message->getParameters()))
        );

        $result = $response->getBody();

        $dom = new \DOMDocument();

        $errorState = libxml_use_internal_errors(true);

        $dom->loadHTML($result);

        libxml_use_internal_errors($errorState);

        $xpath = new \DOMXPath($dom);

        if ($this->isFound($xpath)) {
            yield from $this->postResult($xpath, $response->getRequest()->getUri());
        } else {
            yield from $this->postNoResult($message);
        }
    }

    private function isFound(\DOMXPath $xpath): bool
    {
        return (bool) $xpath->evaluate("//a[@name='SYNOPSIS']")->length;
    }

    private function getName(\DOMXPath $xpath): string {
        return ltrim($xpath->evaluate("//a[@name='NAME']/following-sibling::b/text()")->item(0)->textContent);
    }

    private function getDescription(\DOMXPath $xpath): string {
        return rtrim(str_replace(
            ["\r\n", "\r", "\n"],
            [" ", " ", " "],
            $xpath->evaluate("//a[@name='NAME']/following-sibling::b/following-sibling::text()")->item(0)->textContent
        ));
    }

    private function getSynopsis(\DOMXPath $xpath): string {
        $synopsis = '';

        $currentNode = $xpath->evaluate("//a[@name='SYNOPSIS']/following-sibling::b")->item(0);

        while(!property_exists($currentNode, 'tagName') || $currentNode->tagName !== 'a') {
            $synopsis .= " " . trim($currentNode->textContent);

            $currentNode = $currentNode->nextSibling;
        }

        return rtrim(str_replace(
            ["\r\n", "\r", "\n"],
            [" ", " ", " "],
            trim(preg_replace('/\s+/', ' ', $synopsis))
        ));
    }

    private function postResult(\DOMXPath $xpath, string $url): \Generator {
        yield from $this->chatClient->postMessage(
            sprintf(
                "[ [`%s`%s](%s) ] `%s`",
                $this->getName($xpath),
                $this->getDescription($xpath),
                $url,
                $this->getSynopsis($xpath)
            )
        );
    }

    private function postNoResult(Message $message): \Generator {
        yield from $this->chatClient->postMessage(
            sprintf(
                ":%s %s",
                $message->getOrigin(),
                "Command not found. Have you tried Windows instead? It's great and does all the things!"
            )
        );
    }
}
