<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Storage\Admin as Storage;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;
use function Amp\all;

class Admin implements Plugin
{
    const COMMAND = "admin";

    const ACTIONS = ["add", "remove", "list"];

    private $chatClient;

    private $storage;

    public function __construct(ChatClient $chatClient, Storage $storage) {
        $this->chatClient = $chatClient;
        $this->storage    = $storage;
    }

    public function handle(Message $message): \Generator {
        if (!$this->validMessage($message)) {
            return;
        }

        yield from $this->execute($message);
    }

    private function validMessage(Message $message): bool {
        return $message instanceof Command
            && $message->getCommand() === self::COMMAND
            && $message->getParameters()
            && in_array($message->getParameters()[0], self::ACTIONS, true);
    }

    private function execute(Message $message): \Generator {
        if (!yield from $this->storage->isAdmin($message->getMessage()->getUserId())) {
            yield from $this->chatClient->postMessage(
                sprintf(":%d I'm sorry Dave, I'm afraid I can't do that", $message->getOrigin())
            );

            return;
        }

        if ($message->getParameters()[0] === "list") {
            yield from $this->getList();
        } elseif ($message->getParameters()[0] === "add") {
            yield from $this->add((int) $message->getParameters()[1]);
        } elseif ($message->getParameters()[0] === "remove") {
            yield from $this->remove((int) $message->getParameters()[1]);
        }
    }

    private function getList(): \Generator {
        $userIds = yield from $this->storage->getAll();

        if (!$userIds) {
            return;
        }

        $userData = yield from $this->getUserData($userIds);

        yield from $this->chatClient->postMessage(implode(", ", array_map(function($profile) {
            return sprintf("[%s](%s)", $profile["username"], $profile["profile"]);
        }, $userData)));
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
        $promiseArray = $this->chatClient->requestMulti(array_map(function($userId) {
            return "http://stackoverflow.com/users/$userId/dummy";
        }, $userIds));

        $userProfiles = yield all($promiseArray);

        return $this->parseUserProfiles($userProfiles);
    }

    private function parseUserProfiles(array $userProfiles): array {
        $errorState = libxml_use_internal_errors(true);

        $userData = [];

        foreach ($userProfiles as $profile) {
            $dom = new \DOMDocument();

            $dom->loadHTML($profile->getBody());

            $xpath = new \DOMXPath($dom);

            $userData[] = [
                'username' => trim($xpath->query("//h2[@class='user-card-name']/text()")->item(0)->textContent),
                'profile'  => $xpath->query("//link[@rel='canonical']")->item(0)->getAttribute("href"),
            ];
        }

        libxml_use_internal_errors($errorState);

        return $userData;
    }
}
