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

        if (strpos($content, "&lt;?php") !== false
            || preg_match("@(if|for|while|foreach)\\s*\\(@", $content)
            || preg_match("@\\$([a-zA-Z_0-9]+)\\s*=\\s*@", $content)
            || preg_match("@(print|echo)\\s+(&quot;|&#39;)@", $content)) {
            yield from $this->chatClient->postMessage(
                ":{$origin} Please format your code - hit Ctrl+K before sending and have a look at the [FAQ](http://chat.stackoverflow.com/faq)."
            );
        }
    }
}