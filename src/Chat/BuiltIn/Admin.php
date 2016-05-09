<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\BuiltIn;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\BuiltInCommand;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use function Amp\all;
use function Room11\Jeeves\domdocument_process_html_docs;

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
            yield from $this->getList($command);

            return;
        }

        if (!yield from $this->storage->isAdmin($command->getUserId())) {
            yield from $this->chatClient->postReply(
                $command, "I'm sorry Dave, I'm afraid I can't do that"
            );

            return;
        }

        if ($command->getParameter(0) === "add") {
            yield from $this->add($command, (int)$command->getParameter(1));
        } elseif ($command->getParameter(0) === "remove") {
            yield from $this->remove($command, (int)$command->getParameter(1));
        }
    }

    private function getList(Command $command): \Generator {
        $userIds = yield from $this->storage->getAll();

        if (!$userIds) {
            yield from $this->chatClient->postMessage($command->getRoom(), "There are no registered admins");
            return;
        }

        $list = implode(", ", array_map(function($profile) {
            return $profile["username"];
        }, yield from $this->getUserData($userIds)));

        yield from $this->chatClient->postMessage($command->getRoom(), $list);
    }

    private function add(Command $command, int $userId): \Generator {
        yield from $this->storage->add($userId);

        yield from $this->chatClient->postMessage($command->getRoom(), "User added to the admin list.");
    }

    private function remove(Command $command, int $userId): \Generator {
        yield from $this->storage->remove($userId);

        yield from $this->chatClient->postMessage($command->getRoom(), "User removed from the admin list.");
    }

    private function getUserData(array $userIds): \Generator {
        $userProfiles = yield all($this->httpClient->requestMulti(array_map(function($userId) {
            return "http://stackoverflow.com/users/$userId";
        }, $userIds)));

        return $this->parseUserProfiles($userProfiles);
    }

    /**
     * @param HttpResponse[] $userProfiles
     * @return array
     */
    private function parseUserProfiles(array $userProfiles): array {
        $userData = [];

        domdocument_process_html_docs($userProfiles, function(\DOMDocument $dom) use(&$userData) {
            $xpath = new \DOMXPath($dom);

            $usernameNodes = $xpath->query("//h2[@class='user-card-name']/text()");
            $profileNodes = $xpath->query("//link[@rel='canonical']");

            if ($usernameNodes->length > 0 && $profileNodes->length > 0) {
                /** @var \DOMText $usernameNode */
                /** @var \DOMElement $profileNode */

                $usernameNode = $usernameNodes->item(0);
                $profileNode = $profileNodes->item(0);

                $userData[] = [
                    'username' => trim($usernameNode->textContent),
                    'profile'  => $profileNode->getAttribute("href"),
                ];
            }
        });

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
