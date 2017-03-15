<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;

class Wikipedia extends BasePlugin
{
    private const BASE_URL = 'https://en.wikipedia.org/w/api.php';

    private $chatClient;
    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function makeAPIRequest(array $parameters) : Promise
    {
        static $defaultParameters = [
            'action' => 'query',
            'format' => 'json',
        ];

        return $this->httpClient->request(self::BASE_URL . '?' . http_build_query($parameters + $defaultParameters));
    }

    public function search(Command $command)
    {
        if (!$command->hasParameters()) {
            return new Success();
        }

        /** @var HttpResponse $response */
        $response = yield $this->makeAPIRequest([
            'titles' => implode(' ', $command->getParameters()),
        ]);

        $result   = json_try_decode($response->getBody(), true);
        $firstHit = reset($result['query']['pages']);

        if (!isset($firstHit['pageid'])) {
            return $this->chatClient->postReply($command, 'Sorry I couldn\'t find that page.');
        }

        $response = yield $this->makeAPIRequest([
            'prop' => 'info',
            'inprop' => 'url',
            'pageids' => $firstHit['pageid'],
        ]);

        $page = json_try_decode($response->getBody(), true);

        return $this->chatClient->postMessage($command, $page['query']['pages'][$firstHit['pageid']]['canonicalurl']);
    }

    public function getDescription(): string
    {
        return 'Looks up wikipedia entries and posts onebox links';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'wiki')];
    }
}
