<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\Response as ArtaxResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;

class Mdn implements Plugin {
    use CommandOnlyPlugin;

    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    private function getResult(Command $command): \Generator
    {
        /** @var ArtaxResponse $response */
        $response = yield from $this->chatClient->request(
            'https://developer.mozilla.org/en-US/search.json?highlight=false&q=' . rawurlencode(implode('%20', $command->getParameters()))
        );

        $result = json_decode($response->getBody(), true);

        if(isset($result["documents"][0])) {
            $firstHit = $result["documents"][0];
        }

        if(isset($firstHit) && isset($firstHit["id"]) && isset($firstHit["url"])) {
            yield from $this->postResult($firstHit);
        } else {
            yield from $this->postNoResult($command);
        }
    }

    private function postResult(array $result): \Generator
    {
        $message = sprintf("[ [%s](%s) ] %s", $result["title"], $result["url"], $result["excerpt"]);

        yield from $this->chatClient->postMessage($message);
    }

    private function postNoResult(Command $command): \Generator
    {
        yield from $this->chatClient->postReply(
            $command, 'Sorry, I couldn\'t find a page concerning that topic on MDN.'
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
        return ['mdn'];
    }
}
