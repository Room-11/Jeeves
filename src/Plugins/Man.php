<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Amp\Success;
use Room11\StackChat\Client\Client;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Room11\DOMUtils\domdocument_load_html;

class Man extends BasePlugin
{
    private $chatClient;
    private $httpClient;

    public function __construct(Client $chatClient, HttpClient $httpClient) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function isFound(\DOMXPath $xpath): bool
    {
        return (bool) $xpath->evaluate("//a[@name='SYNOPSIS']")->length;
    }

    private function getSymbolName(\DOMXPath $xpath): string {
        return ltrim($xpath->evaluate("//a[@name='NAME']/following-sibling::b/text()")->item(0)->textContent);
    }

    private function getSymbolDescription(\DOMXPath $xpath): string {
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

    private function postResult(Command $command, \DOMXPath $xpath, string $url): Promise {
        return $this->chatClient->postMessage(
            $command,
            sprintf(
                "[ [`%s`%s](%s) ] `%s`",
                $this->getSymbolName($xpath),
                $this->getSymbolDescription($xpath),
                $url,
                $this->getSynopsis($xpath)
            )
        );
    }

    private function postNoResult(Command $command): Promise {
        return $this->chatClient->postReply(
            $command, "Command not found. Have you tried Windows instead? It's great and does all the things!"
        );
    }

    public function search(Command $command)
    {
        if (!$command->hasParameters()) {
            return new Success();
        }

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            "https://man.freebsd.org/" . rawurlencode(implode("%20", $command->getParameters()))
        );

        $dom = domdocument_load_html($response->getBody());
        $xpath = new \DOMXPath($dom);

        if (!$this->isFound($xpath)) {
            return $this->postNoResult($command);
        }

        return $this->postResult($command, $xpath, $response->getRequest()->getUri());
    }

    public function getDescription(): string
    {
        return 'Fetches manual entries from freebsd.org';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'man')];
    }
}
