<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;

class Mdn implements Plugin {
    const COMMAND = 'mdn';

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
        $response = yield from $this->chatClient->request(
            'https://developer.mozilla.org/en-US/search.json?highlight=false&q=' . rawurlencode(implode('%20', $message->getParameters()))
        );

        $result = json_decode($response->getBody(), true);

        if(isset($result["documents"][0])) {
            $firstHit = $result["documents"][0];
        }

        if(isset($firstHit) && isset($firstHit["id"]) && isset($firstHit["url"])) {
            yield from $this->postResult($firstHit);
        } else {
            yield from $this->postNoResult($message);
        }
    }

    private function postResult(array $result): \Generator
    {
        $message = sprintf("[ [%s](%s) ] %s", $result["title"], $result["url"], $result["excerpt"]);

        yield from $this->chatClient->postMessage($message);
    }

    private function postNoResult(Message $message): \Generator
    {
        yield from $this->chatClient->postMessage(
            sprintf(':%s %s', $message->getOrigin(), 'Sorry, I couldn\'t find a page concerning that topic on MDN.')
        );
    }
}
