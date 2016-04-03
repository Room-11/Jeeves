<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;
use Amp\Artax\Response;

class Packagist implements Plugin
{
    const COMMANDS = ['packagist', 'package'];

    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function handle(Message $message): \Generator
    {
        if (!$this->validMessage($message)) {
            return;
        }

        yield from $this->getResult($message);
    }

    private function validMessage(Message $message): bool
    {
        return $message instanceof Command
        && in_array($message->getCommand(), self::COMMANDS, true);
    }

    private function getResult(Message $message): \Generator
    {
        $reply = $message->getOrigin();

        /** @var Command $message */
        $info = explode('/', implode('/', $message->getParameters()), 2);

        if (count($info) !== 2) {
            yield from $this->chatClient->postMessage(
                ":$reply Usage: `!!packagist vendor package`"
            );

            return;
        }

        list ($vendor, $package) = $info;

        $url = 'https://packagist.org/packages/' . urlencode($vendor) . '/' . urldecode($package) . '.json';

        /** @var Response $response */
        $response = yield from $this->chatClient->request($url);

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
        $response = yield from $this->chatClient->request($url);

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($response->getBody());
        libxml_use_internal_errors($internalErrors);

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' packages ')]/li");

        return yield from $this->chatClient->request('https://packagist.org' . $nodes->item(0)->getAttribute('data-url') . '.json');
    }
}