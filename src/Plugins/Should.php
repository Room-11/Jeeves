<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Client\PostFlags;

class Should extends BasePlugin
{
    private const RESPONSES = [
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
        static $expr = '~(\S+?)\s+(.*?),?\sor\s(?:(?:should\s+\1|rather)\s+)?(.*?)(?:\?|$)~i';

        if (!preg_match($expr, implode(" ", $command->getParameters()), $match)) {
            return $this->is($command);
        }

        $yes = $match[2];
        $no = strtolower($match[3]) === 'not' ? 'not ' . $match[2] : $match[3];
        $answer = $this->translatePronouns(random_int(0, 1) ? $yes : $no);

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

        return $this->chatClient->postMessage($command, $reply, $flags);
    }

    function translatePronouns(string $text): string
    {
        static $replacePairs = [
            'iyou' => ['i', 'you'],
            'youi' => ['^you', 'i'],
            'myyour' => ['my', 'your'],
            'yourmy' => ['your', 'my'],
            'meyou' => ['me', 'you'],
            'youme' => ['you', 'me'],
            'myselfyourself' => ['myself', 'yourself'],
            'yourselfmyself' => ['yourself', 'myself'],
            'mineyours' => ['mine', 'yours'],
            'yoursmine' => ['yours', 'mine'],
        ];
        static $expr;

        if (!isset($expr)) {
            $parts = [];

            foreach ($replacePairs as $name => $pair) {
                $parts[] = "\\b(?P<$name>$pair[0])\\b";
            }

            $expr = '#' . implode('|', $parts) . '#i';
        }

        return preg_replace_callback($expr, function($match) use($replacePairs) {
            foreach ($match as $name => $text) {
                if ($text !== '' && isset($replacePairs[$name])) {
                    return $replacePairs[$name][1];
                }
            }

            return 'banana';
        }, $text);
    }

    public function is(Command $command): Promise
    {
        $reply = random_int(0, 1) ? "yes" : "no";

        if (!random_int(0, 15)) {
            $reply = "dunno";
        }

        $reply = $this->getRandomReply($reply);

        return $this->chatClient->postMessage($command, $reply);
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
