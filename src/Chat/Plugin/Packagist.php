<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Amp\Artax\Client as HttpClient;
use Amp\Artax\Response;
use Room11\Jeeves\Chat\Plugin;

class Packagist implements Plugin
{
    use CommandOnlyPlugin;

    private $chatClient;
    /**
     * @var HttpClient
     */
    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getResult(Command $command): \Generator
    {
        $info = explode('/', implode('/', $command->getParameters()), 2);

        if (count($info) !== 2) {
            yield from $this->chatClient->postReply($command, "Usage: `!!packagist vendor package`");
            return;
        }

        list ($vendor, $package) = $info;

        $url = 'https://packagist.org/packages/' . urlencode($vendor) . '/' . urldecode($package) . '.json';

        /** @var Response $response */
        $response = yield $this->httpClient->request($url);

        if ($response->getStatus() !== 200) {
            $response = yield from $this->getResultFromSearchFallback($vendor, $package);
        }

        $data = json_decode($response->getBody());

        yield from $this->chatClient->postMessage(sprintf(
            "[ [%s](%s) ] %s",
            $data->package->name,
            $data->package->repository,
            $data->package->description
        ));
    }

    private function getResultFromSearchFallback(string $vendor, string $package): \Generator {
        $url = 'https://packagist.org/search/?q=' . urlencode($vendor) . '%2F' . urldecode($package);

        /** @var Response $response */
        $response = yield $this->httpClient->request($url);

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($response->getBody());
        libxml_use_internal_errors($internalErrors);

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' packages ')]/li");

        return yield $this->httpClient->request('https://packagist.org' . $nodes->item(0)->getAttribute('data-url') . '.json');
    }

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator
    {
        yield from $this->getResult($command);
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getHandledCommands(): array
    {
        return ['packagist', 'package'];
    }
}
