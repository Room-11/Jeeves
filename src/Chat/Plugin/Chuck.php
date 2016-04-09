<?php declare(strict_types = 1);

/**
 * Implements the Chuck Command.
 * Returns a random Chuck Norris Joke.
*/

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;

class Chuck implements Plugin {
    const COMMAND = "chuck";

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
            && $message->getCommand() === self::COMMAND;
        }


    private function getResult(Message $message): \Generator {
        $response = yield from $this->chatClient->request(
            "http://api.icndb.com/jokes/random/"
        );

        $result = json_decode($response->getBody(), true);
        $this->chatClient->postMessage("not works :(");
        if(isset($result["type"]) && $result["type"] == "success") {
            yield from $this->postMessage($result);
        } else {
            yield from $this->postError($message);
        }
    }

    private function postMessage(array $result): \Generator {
        $joke = htmlspecialchars_decode($result["value"]["joke"]);
        yield from $this->chatClient->postMessage($joke);
    }

    private function postError(Message $message): \Generator {
        $errorMessage = sprintf(":%s %s", $message->getOrigin(), "Ugh, there was some wierd problem while getting the joke.");
        yield from $this->chatClient->postMessage($errorMessage);
    }
}
