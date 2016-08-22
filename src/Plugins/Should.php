<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\PostFlags;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;

class Should extends BasePlugin
{
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

    public function should(Command $command)
    {
        static $expr = '~(\S+?)\s+(.*?)\sor\s(?:(?:should\s+\1|rather)\s+)?(.*?)(?:\?|$)~i';

        if (!preg_match($expr, implode(" ", $command->getParameters()), $match)) {
            return $this->chatClient->postMessage($command->getRoom(), "Dunno.");
        }

        $answer = random_int(0, 1) ? $match[2] : $match[3];

        $flags = PostFlags::NONE;
        if (strtolower($match[1]) === 'i') {
            $target = 'You';
        } else if ($match[1][0] === '@') {
            $target = $match[1];
        } else if (null !== $pingableName = yield $this->chatClient->getPingableName($command->getRoom(), $match[1])) {
            $target = "@{$pingableName}";
            $flags |= PostFlags::ALLOW_PINGS;
        } else {
            $target = $match[1];
        }

        $reply = "{$target} should {$answer}.";

        $reply = str_ireplace(['my', 'me'], ['your', 'you'], $reply);

        return $this->chatClient->postMessage($command->getRoom(), $reply, $flags);
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

    public function getDescription(): string
    {
        return 'Should I write a description or rather not?';
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
