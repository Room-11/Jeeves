<?php

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;

class RFC implements Plugin
{
    use CommandOnlyPlugin;

    private $chatClient;

    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getResult(): \Generator {
        $uri = "https://wiki.php.net/rfc";

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($uri);

        if ($response->getStatus() !== 200) {
            yield from $this->chatClient->postMessage(
                "Nope, we can't have nice things."
            );

            return;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($response->getBody());
        libxml_use_internal_errors($internalErrors);

        $list = $dom->getElementById("in_voting_phase")->nextSibling->nextSibling->getElementsByTagName("ul")->item(0);
        $rfcsInVoting = [];

        foreach ($list->childNodes as $node) {
            if ($node instanceof \DOMText) {
                continue;
            }

            /** @var \DOMElement $node */
            /** @var \DOMElement $href */
            $href = $node->getElementsByTagName("a")->item(0);

            $rfcsInVoting[] = sprintf(
                "[%s](%s)",
                $href->textContent,
                \Sabre\Uri\resolve($uri, $href->getAttribute("href"))
            );
        }

        if (empty($rfcsInVoting)) {
            yield from $this->chatClient->postMessage("Sorry, but we can't have nice things.");

            return;
        }

        yield from $this->chatClient->postMessage(sprintf(
            "[tag:rfc-vote] %s",
            implode(" | ", $rfcsInVoting)
        ));
    }

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator
    {
        yield from $this->getResult();
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getHandledCommands(): array
    {
        return ['rfcs'];
    }
}
