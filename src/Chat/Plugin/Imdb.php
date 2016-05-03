<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Amp\Artax\Response;
use Room11\Jeeves\Chat\Plugin;

class Imdb implements Plugin
{
    use CommandOnlyPlugin;

    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    private function getResult(Command $command): \Generator
    {
        $response = yield from $this->chatClient->request(
            'http://www.imdb.com/xml/find?xml=1&nr=1&tt=on&q=' . rawurlencode(implode(' ', $command->getParameters()))
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

        /** @var \DOMElement $result */
        $result = $dom->getElementsByTagName('imdbentity')->item(0);

        return sprintf(
            '[ [%s](%s) ] %s',
            $result->firstChild->wholeText,
            'http://www.imdb.com/title/' . $result->getAttribute('id'),
            $result->getElementsByTagName('description')->item(0)->textContent
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
        return ['imdb'];
    }
}
