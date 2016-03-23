<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;

class Canon implements Plugin
{
    const COMMAND = "canon";

    // we need shortened links because otherwise we will hit the chat message length
    const CANONS = [
        "errors" => [
            "stackoverflow" => "http://stackoverflow.com/questions/845021/how-to-get-useful-error-messages-in-php",
            "bitly"         => "http://bit.ly/1SvLc9Q",
        ],
        "headers" => [
            "stackoverflow" => "http://stackoverflow.com/questions/8028957/how-to-fix-headers-already-sent-error-in-php",
            "bitly"         => "http://bit.ly/1Gh6mzN",
        ],
        "globals" => [
            "stackoverflow" => "http://stackoverflow.com/questions/5166087/php-global-in-functions",
            "bitly"         => "http://bit.ly/1VRfwcu",
        ],
        "utf8" => [
            "stackoverflow" => "http://stackoverflow.com/questions/279170/utf-8-all-the-way-through",
            "bitly"         => "http://bit.ly/20JrAA4",
        ],
        "parse html" => [
            "stackoverflow" => "http://stackoverflow.com/questions/3577641/how-do-you-parse-and-process-html-xml-in-php",
            "bitly"         => "http://bit.ly/1SLCXET",
        ],
        "sqli" => [
            "stackoverflow" => "http://stackoverflow.com/questions/60174/how-can-i-prevent-sql-injection-in-php",
            "bitly"         => "http://bit.ly/23LFBQb",
        ],
    ];

    private $chatClient;

    public function __construct(ChatClient $chatClient) {
        $this->chatClient = $chatClient;
    }

    public function handle(Message $message): \Generator {
        if (!$this->validMessage($message)) {
            return;
        }

        /** @var Command $message */
        yield from $this->getResult($message);
    }

    private function validMessage(Message $message): bool {
        return $message instanceof Command
            && $message->getCommand() === self::COMMAND
            && $message->getParameters();
    }

    private function getResult(Command $message): \Generator {
        if ($message->getParameters()[0] === "list") {
            yield from $this->chatClient->postMessage(
                $this->getSupportedCanonicalsMessage()
            );
        } else {
            yield from $this->chatClient->postMessage(
                $this->getMessage(implode(" ", $message->getParameters()))
            );
        }
    }

    private function getSupportedCanonicalsMessage(): string {
        $delimiter = "";
        $message = "The following canonicals are currently supported:";

        foreach (self::CANONS as $parameter => $urls) {
            $message .= sprintf("$delimiter [%s](%s)", $parameter, $urls["bitly"]);

            $delimiter = " -";
        }

        return $message;
    }

    private function getMessage(string $keyword): string {
        if (!array_key_exists(strtolower($keyword), self::CANONS)) {
            return "Cannot find the canon for you... :-( Use `!!canon list` to list all supported canonicals.";
        }

        return self::CANONS[strtolower($keyword)]["stackoverflow"];
    }
}
