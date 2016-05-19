<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\BuiltIn;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\BuiltInCommand;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use function Amp\all;
use function Amp\resolve;
use function Room11\DOMUtils\domdocument_process_html_docs;

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

    private function list(CommandMessage $command): Promise
    {
        return resolve(function() use($command) {
            $admins = yield $this->storage->getAll($command->getRoom());

            if ($admins['owners'] === [] && $admins['admins'] === []) {
                return $this->chatClient->postMessage($command->getRoom(), "There are no registered admins");
            }

            $userIds = array_merge($admins['owners'], $admins['admins']);
            $usernames = array_map(function($profile) {
                return $profile["username"];
            }, yield from $this->getUserData($userIds));

            sort($usernames, SORT_ASC);

            $list = implode(", ", $usernames);

            return $this->chatClient->postMessage($command->getRoom(), $list);
        });
    }

    private function add(CommandMessage $command, int $userId): Promise
    {
        return resolve(function() use($command, $userId) {
            yield $this->storage->add($command->getRoom(), $userId);

            return $this->chatClient->postMessage($command->getRoom(), "User added to the admin list.");
        });
    }

    private function remove(CommandMessage $command, int $userId): Promise
    {
        return resolve(function() use($command, $userId) {
            yield $this->storage->remove($command->getRoom(), $userId);

            return $this->chatClient->postMessage($command->getRoom(), "User removed from the admin list.");
        });
    }

    private function getUserData(array $userIds): \Generator {
        $profileURLs = array_map(function($userId) {
            return "http://stackoverflow.com/users/$userId"; // todo multi-site
        }, $userIds);

        $userProfiles = yield all($this->httpClient->requestMulti($profileURLs));

        return $this->parseUserProfiles(array_map(function(HttpResponse $response) {
            return $response->getBody();
        }, $userProfiles));
    }

    /**
     * @param string[] $userProfiles
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
     * @param CommandMessage $command
     * @return Promise
     */
    public function handleCommand(CommandMessage $command): Promise
    {
        if (!in_array($command->getParameter(0), self::ACTIONS, true)) {
            return new Success();
        }

        if ($command->getParameter(0) === "list") {
            return $this->list($command);
        }

        return resolve(function() use($command) {
            if (!yield $this->storage->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            }

            switch ($command->getParameter(0)) {
                case 'add':    return $this->add($command, (int)$command->getParameter(1));
                case 'remove': return $this->remove($command, (int)$command->getParameter(1));
            }

            throw new \LogicException('Operation ' . $command->getParameter(0) . ' was considered valid but not handled??');
        });
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
