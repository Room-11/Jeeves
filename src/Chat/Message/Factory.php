<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Storage\CommandAlias as CommandAliasStorage;
use function Amp\resolve;

class Factory
{
    private $aliasMap;

    public function __construct(CommandAliasStorage $aliasStorage)
    {
        $this->aliasMap = $aliasStorage;
    }

    private function buildCommand(MessageEvent $event)
    {
        $aliasMapping = preg_match('#^!!(\S+)#u', $event->getMessageContent()->textContent, $match)
            ? yield $this->aliasMap->get($event->getRoom(), strtolower($match[1]))
            : null;

        return new Command($event, $event->getRoom(), $aliasMapping);
    }

    private function isCommandMessage(MessageEvent $event)
    {
        return strpos($event->getMessageContent()->textContent, '!!') === 0;
    }

    public function build(MessageEvent $event): Promise
    {
        return $this->isCommandMessage($event)
            ? resolve($this->buildCommand($event))
            : new Success(new Message($event, $event->getRoom()));
    }
}
