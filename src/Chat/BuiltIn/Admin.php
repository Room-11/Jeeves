<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\BuiltIn;

use Amp\Artax\HttpClient;
use Amp\Promise;
use Room11\Jeeves\Chat\BuiltInCommand;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use function Amp\all;

class Admin implements BuiltInCommand
{
    const ACTIONS = ["add", "remove", "list"];

    private $chatClient;

    private $storage;
    /**
     * @var HttpClient
     */
    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, AdminStorage $storage) {
        $this->chatClient = $chatClient;
        $this->storage    = $storage;
        $this->httpClient = $httpClient;
    }

    private function execute(Command $command): \Generator {
        if ($command->getParameter(0) === "list") {
            yield from $this->getList();

            return;
        }

        if (!yield from $this->storage->isAdmin($command->getUserId())) {
            yield from $this->chatClient->postReply(
                $command, "I'm sorry Dave, I'm afraid I can't do that"
            );

            return;
        }

        if ($command->getParameter(0) === "add") {
            yield from $this->add((int)$command->getParameter(1));
        } elseif ($command->getParameter(0) === "remove") {
            yield from $this->remove((int)$command->getParameter(1));
        }
    }

    private function getList(): \Generator {
        $userIds = yield from $this->storage->getAll();

        if (!$userIds) {
            yield from $this->chatClient->postMessage("There are no registered admins");
            return;
        }

        $list = implode(", ", array_map(function($profile) {
            return $profile["username"];
        }, yield from $this->getUserData($userIds)));

        yield from $this->chatClient->postMessage($list);
    }

    private function add(int $userId): \Generator {
        yield from $this->storage->add($userId);

        yield from $this->chatClient->postMessage("User added to the admin list.");
    }

    private function remove(int $userId): \Generator {
        yield from $this->storage->remove($userId);

        yield from $this->chatClient->postMessage("User removed from the admin list.");
    }

    private function getUserData(array $userIds): \Generator {
        /** @var Promise[] $promiseArray */
        $userProfiles = yield all($this->httpClient->requestMulti(array_map(function($userId) {
            return "http://stackoverflow.com/users/$userId";
        }, $userIds)));

        return $this->parseUserProfiles($userProfiles);
    }

    private function parseUserProfiles(array $userProfiles): array {
        $errorState = libxml_use_internal_errors(true);

        $userData = [];

        foreach ($userProfiles as $profile) {
            $dom = new \DOMDocument();

            // load data in the correct encoding
            // http://chat.stackoverflow.com/transcript/11?m=28980409#28980409
            $dom->loadHTML('<?xml encoding="UTF-8">' . $profile->getBody());

            $xpath = new \DOMXPath($dom);

            $userData[] = [
                'username' => trim($xpath->query("//h2[@class='user-card-name']/text()")->item(0)->textContent),
                'profile'  => $xpath->query("//link[@rel='canonical']")->item(0)->getAttribute("href"),
            ];
        }

        libxml_use_internal_errors($errorState);

        return $userData;
    }

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator
    {
        if (in_array($command->getParameter(0), self::ACTIONS, true)) {
            yield from $this->execute($command);
        }
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getCommandNames(): array
    {
        return ['admin'];
    }
}
