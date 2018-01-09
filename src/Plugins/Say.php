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

class Say extends BasePlugin
{
    private const PRINTF_AUGMENTATIONS = [
        'p' => 'pingableFormatter',
        'r' => 'urlFormatter',
    ];

    private $chatClient;
    private $textFormatter;

    private function pingableFormatter(PrintfSpecifier $specifier, int $argIndex, ChatRoom $room): Promise
    {
        return \Amp\resolve(function() use($specifier, $argIndex, $room) {
            $operation = $specifier->getOperation();
            $operation->transmuteFormatSpecifierType($specifier, 's');

            if (!$operation->hasArg($argIndex)) {
                return;
            }

            $argValue = (string)$operation->getArgValue($argIndex);

            // The user was pinged by the command message, don't ping them again
            if ($argValue[0] === '@') {
                $operation->setArgValue($argIndex, $this->textFormatter->stripPingsFromText($argValue));
                return;
            }

            // Check that the name is actually pingable and resolve the correct casing
            if (null !== $pingable = yield $this->chatClient->getPingableName($room, $argValue)) {
                $operation->setArgValue($argIndex, '@' . $pingable);
            }
        });
    }

    private function urlFormatter(PrintfSpecifier $specifier, int $argIndex): Promise
    {
        $operation = $specifier->getOperation();
        $operation->transmuteFormatSpecifierType($specifier, 's');

        if ($operation->hasArg($argIndex)) {
            $operation->setArgValue($argIndex, rawurlencode($operation->getArgValue($argIndex)));
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

    /**
     * @param Command $command
     * @return string
     * @throws InvalidMessageFormatException
     */
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
     * @param ChatRoom $room
     * @param array $components
     * @return array|\Generator
     * @uses pingableFormatter
     * @uses urlFormatter
     */
    private function preProcessFormatSpecifiers(ChatRoom $room, array $components)
    {
        $operation = new PrintfOperation($components[0], array_slice($components, 1));

        foreach ($operation->getSpecifiers() as $i => $specifier) {
            if (($specifier->getPadWidth() ?? 0) > TextFormatter::TRUNCATION_LIMIT
                || ($specifier->getPrecision() ?? 0) > TextFormatter::TRUNCATION_LIMIT) {
                throw new \InvalidArgumentException;
            }

            if (\array_key_exists($specifier->getType(), self::PRINTF_AUGMENTATIONS)) {
                $callback = [$this, self::PRINTF_AUGMENTATIONS[$specifier->getType()]];
                yield $callback($specifier, $specifier->getArgIndex() ?? $i, $room);
            }
        }

        return [$operation->formatString, $operation->args];
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

final class PrintfOperation
{
    private const EXPR = /** @lang text */ "/
        %
        (?: (?<position> [0-9]+ ) \\$)?
        (?<sign> \\+ )?
        (?<padchar> \\x20|0|'. )?
        (?<alignment> - )?
        (?<padwidth> [0-9]+ )?
        (?<precision> \\.[0-9]* )?
        (?P<type> . )
    /x";

    public $formatString;
    public $args;

    public function __construct(string $formatString, array $args)
    {
        $this->formatString = $formatString;
        $this->args = $args;
    }

    public function transmuteFormatSpecifierType(PrintfSpecifier $specifier, string $newTypeSpecifier): void
    {
        $this->formatString = \substr_replace($this->formatString, $newTypeSpecifier, $specifier->getTypeSpecifierOffset(), 1);
    }

    public function getFormatString(): string
    {
        return $this->formatString;
    }

    public function getArgCount(): int
    {
        return \count($this->args);
    }

    public function hasArg(int $index): bool
    {
        return isset($this->args[$index]);
    }

    public function getArgValue(int $index)
    {
        return $this->args[$index] ?? null;
    }

    public function setArgValue(int $index, $value): void
    {
        $this->args[$index] = $value;
    }

    public function removeArg(int $index): void
    {
        \array_splice($this->args, $index, 1);
    }

    /**
     * @return \Generator|PrintfSpecifier[]
     */
    public function getSpecifiers(): \Generator
    {
        if (!\preg_match_all(self::EXPR, $this->formatString, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $i => $match) {
            yield $i => new PrintfSpecifier($this, $match);
        }
    }
}

final class PrintfSpecifier
{
    private $operation;

    private $value;
    private $offset;
    private $length;

    private $argIndex;
    private $argIndexSpecifierOffset;
    private $argIndexSpecifierLength;

    private $forceSign;
    private $signSpecifierOffset;
    private $signSpecifierLength;

    private $padChar;
    private $padCharSpecifierOffset;
    private $padCharSpecifierLength;

    private $leftAligned;
    private $alignmentSpecifierOffset;
    private $alignmentSpecifierLength;

    private $padWidth;
    private $padWidthSpecifierOffset;
    private $padWidthSpecifierLength;

    private $precision;
    private $precisionSpecifierOffset;
    private $precisionSpecifierLength;

    private $type;
    private $typeSpecifierOffset;
    private $typeSpecifierLength;

    private function setValue(string $specifier, int $offset): void
    {
        $this->value = $specifier;
        $this->offset = $offset;
        $this->length = \strlen($specifier);
    }

    private function setArgIndexSpecifier(string $specifier, int $offset): void
    {
        $this->argIndex = $specifier !== '' ? ((int)$specifier) - 1 : null;
        $this->argIndexSpecifierOffset = $offset;
        $this->argIndexSpecifierLength = \strlen($specifier);
    }

    private function setSignSpecifier(string $specifier, int $offset): void
    {
        $this->forceSign = $specifier === '+';
        $this->signSpecifierOffset = (int)$offset;
        $this->signSpecifierLength = \strlen($specifier);
    }

    private function setPadCharSpecifier(string $specifier, int $offset): void
    {
        $this->padChar = $specifier !== '' ? $specifier[(int)($specifier[0] === "'")] : null;
        $this->padCharSpecifierOffset = $offset;
        $this->padCharSpecifierLength = \strlen($specifier);
    }

    private function setAlignmentSpecifier(string $specifier, int $offset): void
    {
        $this->leftAligned = $specifier === '=';
        $this->alignmentSpecifierOffset = $offset;
        $this->alignmentSpecifierLength = \strlen($specifier);
    }

    private function setPadWidthSpecifier(string $specifier, int $offset): void
    {
        $this->padWidth = $specifier !== '' ? (int)$specifier : null;
        $this->padWidthSpecifierOffset = $offset;
        $this->padWidthSpecifierLength = \strlen($specifier);
    }

    private function setPrecisionSpecifier(string $specifier, int $offset): void
    {
        $this->precision = $specifier !== '' ? (int)\substr($specifier, 1) : null;
        $this->precisionSpecifierOffset = $offset;
        $this->precisionSpecifierLength = \strlen($specifier);
    }

    private function setTypeSpecifier(string $specifier, int $offset): void
    {
        $this->type = $specifier;
        $this->typeSpecifierOffset = $offset;
        $this->typeSpecifierLength = \strlen($specifier);
    }

    public function __construct(PrintfOperation $operation, array $match)
    {
        $this->operation = $operation;
        $this->setValue(...$match[0]);
        $this->setArgIndexSpecifier(...$match['position']);
        $this->setSignSpecifier(...$match['sign']);
        $this->setPadCharSpecifier(...$match['padchar']);
        $this->setAlignmentSpecifier(...$match['alignment']);
        $this->setPadWidthSpecifier(...$match['padwidth']);
        $this->setPrecisionSpecifier(...$match['precision']);
        $this->setTypeSpecifier(...$match['type']);
    }

    public function getOperation(): PrintfOperation
    {
        return $this->operation;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function hasArgIndexSpecifier(): bool
    {
        return $this->argIndexSpecifierOffset !== -1;
    }

    public function getArgIndex(): ?int
    {
        return $this->argIndex;
    }

    public function getArgIndexSpecifierOffset(): int
    {
        return $this->argIndexSpecifierOffset;
    }

    public function getArgIndexSpecifierLength(): int
    {
        return $this->argIndexSpecifierLength;
    }

    public function hasSignSpecifier(): bool
    {
        return $this->signSpecifierOffset !== -1;
    }

    public function shouldForceSign(): bool
    {
        return $this->forceSign;
    }

    public function getSignSpecifierOffset(): int
    {
        return $this->signSpecifierOffset;
    }

    public function getSignSpecifierLength(): int
    {
        return $this->signSpecifierLength;
    }

    public function hasPadCharSpecifier(): bool
    {
        return $this->padCharSpecifierOffset !== -1;
    }

    public function getPadChar(): ?string
    {
        return $this->padChar;
    }

    public function getPadCharSpecifierOffset(): int
    {
        return $this->padCharSpecifierOffset;
    }

    public function getPadCharSpecifierLength(): int
    {
        return $this->padCharSpecifierLength;
    }

    public function hasAlignmentSpecifier(): bool
    {
        return $this->alignmentSpecifierOffset !== -1;
    }

    public function isLeftAligned(): bool
    {
        return $this->leftAligned;
    }

    public function getAlignmentSpecifierOffset(): int
    {
        return $this->alignmentSpecifierOffset;
    }

    public function getAlignmentSpecifierLength(): int
    {
        return $this->alignmentSpecifierLength;
    }

    public function hasPadWidthSpecifier(): bool
    {
        return $this->padWidthSpecifierOffset !== -1;
    }

    public function getPadWidth(): ?int
    {
        return $this->padWidth;
    }

    public function getPadWidthSpecifierOffset(): int
    {
        return $this->padWidthSpecifierOffset;
    }

    public function getPadWidthSpecifierLength(): int
    {
        return $this->padWidthSpecifierLength;
    }

    public function hasPrecisionSpecifier(): bool
    {
        return $this->precisionSpecifierOffset !== -1;
    }

    public function getPrecision(): ?int
    {
        return $this->precision;
    }

    public function getPrecisionSpecifierOffset(): int
    {
        return $this->precisionSpecifierOffset;
    }

    public function getPrecisionSpecifierLength(): int
    {
        return $this->precisionSpecifierLength;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTypeSpecifierOffset(): int
    {
        return $this->typeSpecifierOffset;
    }

    public function getTypeSpecifierLength(): int
    {
        return $this->typeSpecifierLength;
    }
}
