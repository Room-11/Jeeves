<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat;

use Amp\Promise;
use Room11\Jeeves\Storage\CommandAlias as CommandAliasStorage;
use Room11\StackChat\Entities\ChatMessage;
use function Amp\resolve;

class CommandFactory
{
    const INVOKER = '!!'; // todo: make this configurable

    private $aliasMap;

    public function __construct(CommandAliasStorage $aliasStorage)
    {
        $this->aliasMap = $aliasStorage;
    }

    private function splitCommandText(string $commandText, string $invoker)
    {
        preg_match('#^' . preg_quote($invoker, '#') . '(\S+)(.*)#u', $commandText, $match);

        return [strtolower($match[1]), $match[2] ?? ''];
    }

    private function buildCommandFromMessage(ChatMessage $message)
    {
        $commandText = $message->getHTML()->textContent;

        list($commandName, $parameterString) = $this->splitCommandText($commandText, self::INVOKER);
        $originalCommandName = strtolower($commandName);

        while (null !== $alias = yield $this->aliasMap->get($message->getRoom(), $commandName)) {
            $replaceExpr = '#(?<=^' . preg_quote(self::INVOKER, '#') . ')' . preg_quote($commandName, '#') . '#u';
            $commandText = preg_replace($replaceExpr, $alias, $commandText);

            list($commandName, $parameterString) = $this->splitCommandText($commandText, self::INVOKER);

            // Prevent circular aliases
            if (strtolower($commandName) === $originalCommandName) {
                break;
            }
        }

        $parameters = preg_split('#\s+#', $parameterString, -1, PREG_SPLIT_NO_EMPTY);

        return new Command($message->getRoom(), $commandName, $parameters, $message);
    }

    public function isCommandMessage(ChatMessage $message)
    {
        $messageElement = $message->getHTML();

        return $messageElement->firstChild instanceof \DOMText
            && strpos($messageElement->textContent, self::INVOKER) === 0;
    }

    public function buildCommand(ChatMessage $message): Promise
    {
        return resolve($this->buildCommandFromMessage($message));
    }
}
