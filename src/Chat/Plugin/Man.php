<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use function Room11\Jeeves\domdocument_load_html;

class Man implements Plugin
{
    use CommandOnlyPlugin;

    private $chatClient;

    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getResult(Command $command): \Generator {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            "https://man.freebsd.org/" . rawurlencode(implode("%20", $command->getParameters()))
        );

        $dom = domdocument_load_html($response->getBody());
        $xpath = new \DOMXPath($dom);

        if ($this->isFound($xpath)) {
            yield from $this->postResult($command, $xpath, $response->getRequest()->getUri());
        } else {
            yield from $this->postNoResult($command);
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

    private function postResult(Command $command, \DOMXPath $xpath, string $url): \Generator {
        yield from $this->chatClient->postMessage(
            $command->getRoom(),
            sprintf(
                "[ [`%s`%s](%s) ] `%s`",
                $this->getName($xpath),
                $this->getDescription($xpath),
                $url,
                $this->getSynopsis($xpath)
            )
        );
    }

    private function postNoResult(Command $command): \Generator {
        yield from $this->chatClient->postReply(
            $command, "Command not found. Have you tried Windows instead? It's great and does all the things!"
        );
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
        return ['man'];
    }
}
