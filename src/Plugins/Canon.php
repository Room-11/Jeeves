<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\Chars;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Amp\all;
use function Amp\resolve;
use function Room11\Jeeves\getNormalisedStackExchangeURL;

class Canon extends BasePlugin
{
    private $chatClient;
    private $storage;
    private $admin;

    const USAGE = "Usage: `!!canon [ list | add <title> <url> | remove <title> ]`";
    const ACTIONS = ['add', 'remove', 'fire'];

    public function __construct(ChatClient $chatClient, KeyValueStore $storage, AdminStorage $admin) {
        $this->chatClient = $chatClient;
        $this->storage = $storage;
        $this->admin = $admin;
    }

    private function getSupportedCanonicals(Command $command): Promise
    {
        return resolve(function() use($command) {
            $message = "The following canonicals are currently supported:";

            $canonicals = yield $this->storage->getAll($command->getRoom());

            if ($canonicals === []) {
                return $this->chatClient->postMessage($command->getRoom(), "There are no registered canonicals.");
            }

            foreach ($canonicals as $title => $link) {
                $message .= sprintf(
                    "\n%s %s %s %s",
                    Chars::BULLET,
                    $title,
                    Chars::RIGHTWARDS_ARROW,
                    $link["stackoverflow"]
                );
            }

            return $this->chatClient->postMessage($command->getRoom(), $message);
        });
    }

    private function getMessage(Command $command, string $keyword): Promise
    {
        return resolve(function() use($command, $keyword) {
            if ($command->hasParameters() === false) {
                return $this->chatClient->postMessage($command->getRoom(), self::USAGE);
            }

            if (false === yield $this->storage->exists(strtolower($keyword), $command->getRoom())) {
                return $this->chatClient->postMessage($command->getRoom(), "Cannot find the canon for you... :-( Use `!!canon list` to list all supported canonicals.");
            }

            if ($canon = yield $this->storage->get(strtolower($keyword), $command->getRoom())) {
                return $this->chatClient->postMessage($command->getRoom(), $canon["stackoverflow"]);
            }

            throw new \LogicException('Operation ' . $command->getParameter(0) . ' was considered valid but not handled??');
        });
    }

    private function add(Command $command, string $canonTitle, string $url): Promise
    {   // !!canon add mysql http://stackoverflow.com/questions/12859942
        $url = getNormalisedStackExchangeURL($url);

        return resolve(function() use($command, $canonTitle, $url) {

            if(!$command->hasParameters(3)){
                return $this->chatClient->postMessage($command->getRoom(), self::USAGE);
            }

            $canonicals = yield $this->storage->getKeys($command->getRoom());

            if (in_array($canonTitle, $canonicals)) {
                return $this->chatClient->postMessage($command->getRoom(), "$canonTitle is already on canonicals.");
            }

            $value = [ 'stackoverflow' => $url ];
            yield $this->storage->set($canonTitle, $value, $command->getRoom());

            return $this->chatClient->postMessage($command->getRoom(), "Cannonball in place! I mean.. canonical was added successfully.");
        });
    }

    private function remove(Command $command, string $canonTitle): Promise
    {   // !!canon remove mysql

        return resolve(function() use($command, $canonTitle) {
            if(!$command->hasParameters(2)){
                return $this->chatClient->postMessage($command->getRoom(), self::USAGE);
            }

            $canonicals = yield $this->storage->getAll($command->getRoom());

            if (!in_array($canonTitle, array_keys($canonicals))) {
                return $this->chatClient->postMessage($command->getRoom(), "Canonical is not on the list.");
            }

            yield $this->storage->unset($canonTitle, $command->getRoom());

            return $this->chatClient->postMessage($command->getRoom(), "Canonical removed from the list.");
        });
    }

    public function fire(Command $command): Promise
    {  // !!canon fire
        return resolve(function () use($command){
            return $this->chatClient->postMessage($command->getRoom(), "http://i.imgur.com/s7gEZZC.gif");
        });
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
            return $this->getSupportedCanonicals($command);
        }

        if(!in_array($command->getParameter(0), self::ACTIONS)){
            return $this->getMessage($command, implode(" ", $command->getParameters()));
        }

        return resolve(function() use($command) {
            if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that.");
            }

            switch ($command->getParameter(0)) {
                case 'add':    return yield $this->add($command, (string)$command->getParameter(1), (string)$command->getParameter(2));
                case 'remove': return yield $this->remove($command, (string)$command->getParameter(1));
                case 'fire': return yield $this->fire($command);
            }
        });
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
