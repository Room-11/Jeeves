<?php  declare(strict_types=1);
namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Event\NewMessage;
use Room11\Jeeves\Chat\Message\Message;

class CodeFormat extends BasePlugin
{
    private $chatClient;

    public function __construct(ChatClient $chatClient) {
        $this->chatClient = $chatClient;
    }

    private function validMessage(Message $message): bool {
        return get_class($message) === Message::class
            && $message->getEvent() instanceof NewMessage;
    }

    public function handleMessage(Message $message): Promise {
        if (!$this->validMessage($message)) {
            return new Success();
        }

        $content = $message->getText();
        $origin = $message->getId();

        # Message is already formatted as code
        if (strpos($content, "<pre class='full'>") !== false) {
            return new Success();
        }

        # Check only multiline messages
        if (strpos($content, "<div class='full'>") === false) {
            return new Success();
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

        if ($linesOfCode < 3) {
            return new Success();
        }

        return $this->chatClient->postMessage(
            $message->getRoom(),
            ":{$origin} Please format your code - hit Ctrl+K before sending and have a look at the [FAQ](http://chat.stackoverflow.com/faq)."
        );
    }

    public function getDescription(): string
    {
        return 'Asks users to format their code when unformatted multi-line code blocks are posted';
    }

    /**
     * @return callable|null
     */
    public function getMessageHandler()
    {
        return [$this, 'handleMessage'];
    }
}
