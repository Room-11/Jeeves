<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;

class Urban extends BasePlugin
{
    private $chatClient;
    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    // @todo make this "global"
    public function normalizeMessage(string $message): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $message));
    }

    private function getMessage(array $result): string
    {
        if ($result['result_type'] === 'no_results')
        {
            return 'whatchoo talkin bout willis';
        }

        return $this->normalizeMessage(sprintf(
            '[ [%s](%s) ] %s',
            trim($result['list'][0]['word']),
            $result['list'][0]['permalink'],
            str_replace("\r\n", ' ', $result['list'][0]['definition'])
        ));
    }

    public function search(Command $command): \Generator
    {
        if (!$command->hasParameters()) {
            return new Success();
        }

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            'http://api.urbandictionary.com/v0/define?term=' . rawurlencode(implode(' ', $command->getParameters()))
        );

        $result = json_decode($response->getBody(), true);

        return $this->chatClient->postMessage($command->getRoom(), $this->getMessage($result));
    }

    public function getDescription(): string
    {
        return 'Looks up entries from urbandictionary.com';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'urban')];
    }
}
