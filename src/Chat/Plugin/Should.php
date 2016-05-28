<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\CommandOnly;
use Room11\Jeeves\Chat\PluginCommandEndpoint;

class Should implements Plugin
{
    use CommandOnly;

    const RESPONSES = [
        "yes" => [
            "Yes.",
            "I think so.",
            "God, yes!",
        ],
        "no" => [
            "No.",
            "[nooooooooooooooo](http://www.nooooooooooooooo.com/)!",
            "Let me think about it … wait … yes … well actually, no.",
        ],
        "dunno" => [
            "Dunno.",
            "No idea …",
            "What the hell are you talking about?",
            "Let me think about it … dunno …",
            "I think you know the answer already.",
        ],
    ];

    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function should(Command $command): Promise
    {
        if (preg_match('~(\S+?) (.*?) or (.*?)(?:\?|$)~i', implode(" ", $command->getParameters()), $match)) {
            $answer = random_int(0, 1) ? $match[2] : $match[3];
            $person = strtolower($match[1]) === "i" ? "You" : "@{$match[1]}";
            $reply = "{$person} should {$answer}.";

            return $this->chatClient->postMessage($command->getRoom(), $reply);
        }

        return $this->chatClient->postMessage($command->getRoom(), "Dunno.");
    }

    public function is(Command $command): Promise
    {
        $reply = random_int(0, 1) ? "yes" : "no";

        if (!random_int(0, 15)) {
            $reply = "dunno";
        }

        $reply = $this->getRandomReply($reply);

        return $this->chatClient->postMessage($command->getRoom(), $reply);
    }

    private function getRandomReply(string $answer): string
    {
        return self::RESPONSES[$answer][random_int(0, (count(self::RESPONSES[$answer]) - 1))];
    }

    public function getName(): string
    {
        return 'Should';
    }

    public function getDescription(): string
    {
        return 'Should I write a description or rather not?';
    }

    public function getHelpText(array $args): string
    {
        // TODO: Implement getHelpText() method.
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint('Should', [$this, 'should'], 'should'),
            new PluginCommandEndpoint('Is', [$this, 'is'], 'is'),
        ];
    }
}
