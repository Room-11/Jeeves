<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;

class Canon extends BasePlugin
{
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
        "operators" => [
            "stackoverflow" => "http://stackoverflow.com/questions/3737139/reference-what-does-this-symbol-mean-in-php",
            "bitly"         => "http://bit.ly/29AkAGd",
        ],
        "mysql" => [
            "stackoverflow" => "http://stackoverflow.com/questions/12859942/why-shouldnt-i-use-mysql-functions-in-php",
            "bitly"         => "http://bit.ly/2bUpnha",
        ]
    ];

    private $chatClient;

    public function __construct(ChatClient $chatClient) {
        $this->chatClient = $chatClient;
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

    public function getCanonical(Command $command): Promise
    {
        if (!$command->hasParameters()) {
            return new Success();
        }

        return $this->chatClient->postMessage(
            $command->getRoom(),
            $command->getParameter(0) === "list"
                ? $this->getSupportedCanonicalsMessage()
                : $this->getMessage(implode(" ", $command->getParameters()))
        );
    }

    public function getName(): string
    {
        return 'Canonicals';
    }

    public function getDescription(): string
    {
        return 'Posts links to canonical resources on various subjects';
    }

    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('canon', [$this, 'getCanonical'], 'canon')];
    }
}
