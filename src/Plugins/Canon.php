<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\InvalidStackExchangeUrlException;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Chars;
use Room11\StackChat\Client\Client;
use function Amp\resolve;
use function Room11\Jeeves\normalize_stack_exchange_url;

class Canon extends BasePlugin
{
    private $chatClient;
    private $storage;
    private $admin;

    private const USAGE = /** @lang text */ "Usage: `!!canon [ list | add <title> <url> | remove <title> ]`";
    private const ADMIN_ACTIONS = ['add', 'remove', 'fire'];

    public function __construct(Client $chatClient, KeyValueStore $storage, AdminStorage $admin) {
        $this->chatClient = $chatClient;
        $this->storage = $storage;
        $this->admin = $admin;
    }

    private function getSupportedCanonicals(Command $command)
    {
        $message = "The following canonicals are currently supported:";

        $canonicals = yield $this->storage->getAll($command->getRoom());

        if ($canonicals === []) {
            return $this->chatClient->postMessage($command, "There are no registered canonicals.");
        }

        ksort($canonicals);

        foreach ($canonicals as $title => $link) {
            $message .= sprintf(
                "\n%s %s %s %s",
                Chars::BULLET,
                $title,
                Chars::RIGHTWARDS_ARROW,
                $link["stackoverflow"]
            );
        }

        return $this->chatClient->postMessage($command, $message);
    }

    private function getMessage(Command $command)
    {
        if (!$command->hasParameters(1)){
            return $this->chatClient->postMessage($command, self::USAGE);
        }

        $canonTitle = strtolower($command->getParameter(0));

        if (!yield $this->storage->exists($canonTitle, $command->getRoom())) {
            return $this->chatClient->postMessage($command, "Cannot find the canon for you... :-( Use `!!canon list` to list all supported canonicals.");
        }

        $canon = yield $this->storage->get($canonTitle, $command->getRoom());

        return $this->chatClient->postMessage($command, $canon["stackoverflow"]);
    }

    private function add(Command $command)
    {   // !!canon add mysql http://stackoverflow.com/questions/12859942
        if (!$command->hasParameters(3)){
            return $this->chatClient->postMessage($command, self::USAGE);
        }

        $canonTitle = strtolower($command->getParameter(1));

        if (yield $this->storage->exists($canonTitle, $command->getRoom())) {
            return $this->chatClient->postMessage($command, "{$canonTitle} is already on canonicals.");
        }

        try {
            $url = normalize_stack_exchange_url($command->getParameter(2));
        } catch (InvalidStackExchangeUrlException $e) {
            return $this->chatClient->postMessage($command, "Sorry, I don't recognise that as a Stack Exchange URL :-(");
        }

        yield $this->storage->set($canonTitle, ['stackoverflow' => $url], $command->getRoom());

        return $this->chatClient->postMessage($command, "Cannonball in place! I mean... canonical '{$canonTitle}' was added successfully.");
    }

    private function remove(Command $command)
    {   // !!canon remove mysql
        if (!$command->hasParameters(2)){
            return $this->chatClient->postMessage($command, self::USAGE);
        }

        $canonTitle = strtolower($command->getParameter(1));

        if (!yield $this->storage->exists($canonTitle, $command->getRoom())) {
            return $this->chatClient->postMessage($command, "Canonical is not on the list.");
        }

        yield $this->storage->unset($canonTitle, $command->getRoom());

        return $this->chatClient->postMessage($command, "Canonical removed from the list.");
    }

    private function fire(Command $command): Promise
    {  // !!canon fire
        return $this->chatClient->postMessage($command, "http://i.imgur.com/s7gEZZC.gif");
    }

    private function handleAdminAction(Command $command)
    {
        if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
            return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that.");
        }

        switch ($command->getParameter(0)) {
            case 'add':    return yield from $this->add($command);
            case 'remove': return yield from $this->remove($command);
            case 'fire':   return $this->fire($command);
        }

        return null;
    }

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return Promise
     */
    public function handleCommand(Command $command): Promise
    {
        if ($command->getParameter(0) === "list") {
            return resolve($this->getSupportedCanonicals($command));
        }

        if (in_array($command->getParameter(0), self::ADMIN_ACTIONS)){
            return resolve($this->handleAdminAction($command));
        }

        return resolve($this->getMessage($command));
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
        return [new PluginCommandEndpoint('canon', [$this, 'handleCommand'], 'canon')];
    }
}
