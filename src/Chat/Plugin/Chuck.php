<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;

class Chuck implements Plugin {
    use CommandOnlyPlugin;

    private $chatClient;

    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getResult(Command $command): \Generator {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            "http://api.icndb.com/jokes/random/"
        );

        $result = json_decode($response->getBody(), true);


        if(isset($result["type"]) && $result["type"] == "success") {
            $joke = htmlspecialchars_decode($this->skeetify($command, $result["value"]["joke"]));
            yield from $this->chatClient->postMessage($command->getRoom(), $joke);
        } else {
            yield from $this->chatClient->postReply(
                $command, "Ugh, there was some weird problem while getting the joke."
            );
        }
    }

    private function skeetify(Command $command, string $joke): string {
        if ($command->getCommandName() !== "skeet") {
            return $joke;
        }

        return str_replace("Chuck Norris", "Jon Skeet", $joke);
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
        return ["chuck", "skeet"];
    }
}
