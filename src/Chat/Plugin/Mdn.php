<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\CommandOnly;
use Room11\Jeeves\Chat\PluginCommandEndpoint;

class Mdn implements Plugin {
    use CommandOnly;

    private $chatClient;

    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function postResult(Command $command, array $result): \Generator
    {
        $message = sprintf("[ [%s](%s) ] %s", $result["title"], $result["url"], $result["excerpt"]);

        yield from $this->chatClient->postMessage($command->getRoom(), $message);
    }

    private function postNoResult(Command $command): \Generator
    {
        yield from $this->chatClient->postReply(
            $command, 'Sorry, I couldn\'t find a page concerning that topic on MDN.'
        );
    }

    public function search(Command $command): \Generator
    {
        if (!$command->hasParameters()) {
            return;
        }

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            'https://developer.mozilla.org/en-US/search.json?highlight=false&q=' . rawurlencode(implode('%20', $command->getParameters()))
        );

        $result = json_decode($response->getBody(), true);

        if(isset($result["documents"][0])) {
            $firstHit = $result["documents"][0];
        }

        if(isset($firstHit) && isset($firstHit["id"]) && isset($firstHit["url"])) {
            yield from $this->postResult($command, $firstHit);
        } else {
            yield from $this->postNoResult($command);
        }
    }

    public function getName(): string
    {
        return 'MDN';
    }

    public function getDescription(): string
    {
        return 'Fetches manual entries from the Mozilla Developer Network';
    }

    public function getHelpText(array $args): string
    {
        // TODO: Implement getHelpText() method.
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'mdn')];
    }
}
