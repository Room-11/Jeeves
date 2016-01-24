<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\Xhr as ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;
use Amp\Artax\Response;

class Packagist implements Plugin
{
    const COMMAND = 'packagist';

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
        && $message->getCommand() === self::COMMAND
        && $message->getParameters();
    }

    private function getResult(Message $message): \Generator
    {
        /** @var Command $message */
        $info = explode('/', implode('/', $message->getParameters()), 2);

        if (count($info) !== 2) {
            return;
        }

        list ($vendor, $package) = $info;

        $url = 'https://packagist.org/packages/' . urlencode($vendor) . '/' . urldecode($package) . '.json';

        /** @var Response $response */
        $response = yield from $this->chatClient->request($url);

        if ($response->getStatus() !== 200) {
            return;
        }

        $data = json_decode($response->getBody());

        yield from $this->chatClient->postMessage(sprintf(
            "[ [%s](%s) ] %s",
            $data->package->name,
            $data->package->repository,
            $data->package->description
        ));
    }
}