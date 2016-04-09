<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;

class Chuck implements Plugin {
    const COMMANDS = ["chuck", "skeet"];

    private $chatClient;

    public function __construct(ChatClient $chatClient) {
        $this->chatClient = $chatClient;
    }

    public function handle(Message $message): \Generator {
        if (!$this->validMessage($message)) {
            return;
        }

        yield from $this->getResult($message);
    }

    private function validMessage(Message $message): bool {
        return $message instanceof Command
            && in_array($message->getCommand(), self::COMMANDS, true);
    }


    private function getResult(Message $message): \Generator {
        $response = yield from $this->chatClient->request(
            "http://api.icndb.com/jokes/random/"
        );

        $result = json_decode($response->getBody(), true);

        $this->chatClient->postMessage("not works :(");

        if(isset($result["type"]) && $result["type"] == "success") {
            yield from $this->postMessage($this->skeetify($message, $result["value"]["joke"]));
        } else {
            yield from $this->postError($message);
        }
    }

    private function skeetify(Message $message, string $joke): string {
        if ($message->getCommand() !== "skeet") {
            return $joke;
        }

        return str_replace("Chuck Norris", "Jon Skeet", $joke);
    }

    private function postMessage(string $joke): \Generator {
        $joke = htmlspecialchars_decode($joke);

        yield from $this->chatClient->postMessage($joke);
    }

    private function postError(Message $message): \Generator {
        $errorMessage = sprintf(":%s %s", $message->getOrigin(), "Ugh, there was some wierd problem while getting the joke.");

        yield from $this->chatClient->postMessage($errorMessage);
    }
}
