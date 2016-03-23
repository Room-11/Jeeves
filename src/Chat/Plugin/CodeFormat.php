<?php

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Message;
use Room11\Jeeves\Chat\Command\Void;
use Room11\Jeeves\Chat\Message\NewMessage;

class CodeFormat implements Plugin {
    private $chatClient;

    public function __construct(ChatClient $chatClient) {
        $this->chatClient = $chatClient;
    }

    public function handle(Message $message): \Generator {
        if (!$this->validMessage($message)) {
            return;
        }

        /** @var Void $message */
        yield from $this->getResult($message->getMessage());
    }

    private function validMessage(Message $message): bool {
        return $message instanceof Void
        && $message->getMessage() instanceof NewMessage;
    }

    private function getResult(NewMessage $message): \Generator {
        $content = $message->getContent();
        $origin = $message->getId();

        # Message is already formatted as code
        if (strpos($content, "<pre class='full'>") !== false) {
            return;
        }

        # Check only multiline messages
        if (strpos($content, "<div class='full'>") === false) {
            return;
        }

        $lines = str_replace(["<div class='full'>", "</div>"], "", $content);
        $lines = array_map("html_entity_decode", array_map("trim", explode("<br>", $lines)));

        # First line is often text, just check other lines
        array_shift($lines);

        $linesOfCode = array_reduce($lines, function($carry, $line) {
            if (preg_match("@^([A-Za-z]+ ){4,}@", $line)) {
                return $carry;
            }

            if (strpos($line, "<?php") !== false
                || preg_match("@(if|for|while|foreach|switch)\\s*\\(@", $line)
                || preg_match("@\\$([a-zA-Z_0-9]+)@", $line)
                || preg_match("@(print|echo)\\s+(\"|'|\\$)@", $line)) {
                return $carry + 1;
            }

            return $carry;
        }, 0);

        if ($linesOfCode >= 3) {
            yield from $this->chatClient->postMessage(
                ":{$origin} Please format your code - hit Ctrl+K before sending and have a look at the [FAQ](http://chat.stackoverflow.com/faq)."
            );
        }
    }
}
