<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\Exception;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Client\PostFlags;
use Room11\StackChat\Client\TextFormatter;
use Room11\StackChat\Room\Room as ChatRoom;

class InvalidMessageFormatException extends Exception {}

class PrintfContext
{
    public $room;
    public $string;
    public $args;

    public function __construct(ChatRoom $room, string $string, array $args)
    {
        $this->room = $room;
        $this->string = $string;
        $this->args = $args;
    }

    public function transmuteFormatSpecifier(array $match, string $newSpecifier): void
    {
        $this->string = substr_replace($this->string, $newSpecifier, $match[7][1], 1);
    }

    public function getArgIndex(array $match, int $default): int
    {
        return $match[1][0] !== ''
            ? $match[1][0] - 1
            : $default;
    }
}

class Say extends BasePlugin
{
    private const PRINTF_AUGMENTATIONS = [
        'p' => 'pingableFormatter',
        'r' => 'urlFormatter',
    ];

    private $chatClient;
    private $textFormatter;

    private function pingableFormatter(array $match, int $argIndex, PrintfContext $context): Promise
    {
        return \Amp\resolve(function() use($match, $argIndex, $context) {
            $context->transmuteFormatSpecifier($match, 's');

            if (!isset($context->args[$argIndex])) {
                return;
            }

            if ($context->args[$argIndex][0] === '@') {
                $context->args[$argIndex] = $this->textFormatter->stripPingsFromText($context->args[$argIndex]);
            } else if (null !== $pingable = yield $this->chatClient->getPingableName($context->room, $context->args[$argIndex])) {
                $context->args[$argIndex] = '@' . $pingable;
            }
        });
    }

    private function urlFormatter(array $match, int $argIndex, PrintfContext $context): Promise
    {
        $context->transmuteFormatSpecifier($match, 's');

        if (isset($context->args[$argIndex])) {
            $context->args[$argIndex] = rawurldecode($context->args[$argIndex]);
        }

        return new Success();
    }

    public function __construct(ChatClient $chatClient, TextFormatter $textFormatter)
    {
        $this->chatClient = $chatClient;
        $this->textFormatter = $textFormatter;
    }

    public function say(Command $command)
    {
        return $this->chatClient->postMessage($command, $this->getMessageResponse($command));
    }

    public function sayf(Command $command)
    {
        try {
            return $this->chatClient->postMessage(
                $command,
                yield from $this->getFormattedMessageResponse($command),
                PostFlags::ALLOW_PINGS
            );
        } catch (InvalidMessageFormatException $e) {
            return $this->chatClient->postReply($command, $e->getMessage());
        }
    }

    public function reply(Command $command)
    {
        return $this->chatClient->postReply($command, $this->getMessageResponse($command));
    }

    public function replyf(Command $command)
    {
        try {
            return $this->chatClient->postReply(
                $command,
                yield from $this->getFormattedMessageResponse($command),
                PostFlags::ALLOW_PINGS
            );
        } catch (InvalidMessageFormatException $e) {
            return $this->chatClient->postReply($command, $e->getMessage());
        }
    }

    private function getMessageResponse(Command $command): string
    {
        return $this->textFormatter->interpolateEscapeSequences(implode(' ', $command->getParameters()));
    }

    private function getFormattedMessageResponse(Command $command)
    {
        $current = '';
        $components = [];

        foreach ($command->getParameters() as $parameter) {
            if ($parameter !== '/') {
                $current .= str_replace('\\/', '/', $parameter) . ' ';
            } else if ($current !== '') {
                $components[] = trim($current);
                $current = '';
            }
        }

        if ($current !== '') {
            $components[] = trim($current);
        }

        try {
            list($string, $args) = yield from $this->preProcessFormatSpecifiers($command->getRoom(), $components);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidMessageFormatException('Only if you say it first');
        }

        $string = $this->textFormatter->stripPingsFromText($string);
        $string = $this->textFormatter->interpolateEscapeSequences($string);

        if (false === $result = @\vsprintf($string, $args)) {
            throw new InvalidMessageFormatException('printf() failed, check your format string and arguments');
        }

        return $result;
    }

    /**
     * @uses pingableFormatter
     * @uses urlFormatter
     */
    private function preProcessFormatSpecifiers(ChatRoom $room, array $components)
    {
        static $expr = /** @lang RegExp */ "/
          %
          (?: (?<position> [0-9]+ ) \\$)?
          (?<sign> [-+] )?
          (?<padchar> \\x20|0|'. )?
          (?<alignment> - )?
          (?<padwidth> [0-9]+ )?
          (?<precision> \\.[0-9]* )?
          (?<type> . )
        /x";

        $ctx = new PrintfContext($room, $components[0], array_slice($components, 1));

        if (0 === $count = preg_match_all($expr, $ctx->string, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [$ctx->string, $ctx->args];
        }

        foreach ($matches as $i => $match) {
            if (($match[5][0] !== '' && ((int)$match[5][0]) > TextFormatter::TRUNCATION_LIMIT)
                || ($match[6][0] !== '' && ((int)substr($match[6][0], 1)) > TextFormatter::TRUNCATION_LIMIT)) {
                throw new \InvalidArgumentException;
            }

            if (\array_key_exists($match[7][0], self::PRINTF_AUGMENTATIONS)) {
                $argIndex = $match[1][0] !== '' ? $match[1][0] - 1 : $i;
                ([$this, self::PRINTF_AUGMENTATIONS[$match[7][0]]])($match, $argIndex, $ctx);
            }
        }

        return [$ctx->string, $ctx->args];
    }

    public function getDescription(): string
    {
        return 'Mindlessly parrots whatever crap you want';
    }

    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint('say', [$this, 'say'], 'say'),
            new PluginCommandEndpoint('sayf', [$this, 'sayf'], 'sayf', 'Same as say with printf-style formatting, separate format string and args with / slashes'),
            new PluginCommandEndpoint('reply', [$this, 'reply'], 'reply', 'Same as say except it replies to the invoking message'),
            new PluginCommandEndpoint('replyf', [$this, 'replyf'], 'replyf', 'Same as sayf except it replies to the invoking message'),
        ];
    }
}
