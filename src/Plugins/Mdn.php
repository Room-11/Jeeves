<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;

class Mdn extends BasePlugin
{
    private $chatClient;
    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function postResult(Command $command, array $result): Promise
    {
        $message = sprintf("[ [%s](%s) ] %s", $result["title"], $result["url"], $result["excerpt"]);

        return $this->chatClient->postMessage($command, $message);
    }

    private function postNoResult(Command $command): Promise
    {
        return $this->chatClient->postReply(
            $command, 'Sorry, I couldn\'t find a page concerning that topic on MDN.'
        );
    }

    public function search(Command $command)
    {
        if (!$command->hasParameters()) {
            return new Success();
        }

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            'https://developer.mozilla.org/en-US/search.json?highlight=false&q=' . rawurlencode(implode('%20', $command->getParameters()))
        );

        $result = json_decode($response->getBody(), true);

        if(!isset($result["documents"][0]["id"], $result["documents"][0]["url"])) {
            return $this->postNoResult($command);
        }

        return $this->postResult($command, $result["documents"][0]);
    }

    public function getDescription(): string
    {
        return 'Fetches manual entries from the Mozilla Developer Network';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'mdn')];
    }
}
