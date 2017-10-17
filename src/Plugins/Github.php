<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Amp\Success;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\Jeeves\Chat\Command;
use Room11\StackChat\Client\RoomContainer;
use Room11\StackChat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Amp\all;
use function Amp\cancel;
use function Amp\repeat;
use function Amp\resolve;

class Github extends BasePlugin
{
    private $chatClient;
    private $httpClient;
    private $pluginData;
    private $lastKnownStatus  = null;
    private $enabledRooms     = [];
    private $pollingWatcherId = null;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, KeyValueStore $pluginData)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->pluginData = $pluginData;
    }

    public function enableForRoom(ChatRoom $room, bool $persist = true)
    {
        $this->enabledRooms[$room->getId()] = $room;

        if ($this->pollingWatcherId) {
            return;
        }

        $this->pollingWatcherId = repeat(function () {
            yield $this->checkStatusChange();
        }, 150000);
    }

    public function disableForRoom(ChatRoom $room, bool $persist = true)
    {
        unset($this->enabledRooms[$room->getId()]);

        if (!$this->enabledRooms) {
            cancel($this->pollingWatcherId);

            $this->pollingWatcherId = null;
        }
    }

    public function github(Command $command): \Generator
    {
        $obj = $command->getParameter(0) ?? 'status';

        if ($obj === 'status') {
            return yield $this->status($command);
        } elseif (strpos($obj, '/') === false) {
            return yield $this->profile($command, $obj);
        } elseif (strpos($obj, '/') === strrpos($obj, '/')) {
            return yield $this->repo($command, $obj);
        }

        return $this->chatClient->postMessage($command,
            /** @lang text */ "Usage: !!github [status | <project> | <profile> | <profile>/<repo> ]"
        );
    }

    /*
     * Example:
     *   !!github
     *   !!github status
     *
     * [tag:github-status] good: Everything operating normally. as of 2016-05-25T18:44:58Z
     */
    protected function status(Command $command): Promise
    {
        return resolve(function() use ($command) {
            return $this->postResponse($command, yield $this->getGithubStatus());
        });
    }

    private function checkStatusChange(): Promise
    {
        return resolve(function() {
            $githubResponse = yield $this->getGithubStatus();

            if (!$githubResponse) {
                return new Success;
            }

            if ($this->lastKnownStatus === $githubResponse->status) {
                return new Success;
            }

            $before = $this->lastKnownStatus;
            $this->lastKnownStatus = $githubResponse->status;

            if ($before === null) {
                return new Success;
            }

            return all(array_map(function (ChatRoom $room) use ($githubResponse) {
                return $this->postResponse($room, $githubResponse);
            }, $this->enabledRooms));
        });
    }

    /**
     * @param ChatRoom|RoomContainer $target
     * @param $response
     * @return Promise
     */
    private function postResponse($target, $response)
    {
        if (!$response) {
            return $this->chatClient->postMessage($target, "Failed fetching status");
        }

        $messageTemplate = '%s *as of %s*';

        if ($response->status === 'good') {
            $messageTemplate = '[%s *as of %s*](https://www.youtube.com/watch?v=aJFyWBLeM7Q)';
        }

        return $this->chatClient->postMessage(
            $target,
            sprintf(
                "[tag:github-status] **%s**: " . $messageTemplate,
                $response->status,
                rtrim($response->body, '.!?'),
                $response->created_on
            )
        );
    }

    private function getGithubStatus(): Promise
    {
        return resolve(function() {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request('https://status.github.com/api/last-message.json');

            if ($response->getStatus() !== 200) {
                return false;
            }

            return json_decode($response->getBody());
        });
    }

    /*
     * Example:
     *   !!github Room-11
     *
     * [tag:github-profile] Organization [Room-11](https://github.com/Room-11): 15 public repos
     */
    protected function profile(Command $command, string $profile): Promise
    {
        return resolve(function() use ($command, $profile) {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request('https://api.github.com/users/' . urlencode($profile));

            if ($response->getStatus() !== 200) {
                return $this->chatClient->postMessage($command, "Failed fetching profile for {$profile}");
            }

            $json = json_decode($response->getBody());
            if (!isset($json->id)) {
                return $this->chatClient->postMessage($command, "Unknown profile {$profile}");
            }

            return $this->chatClient->postMessage(
                $command,
                sprintf(
                    "[tag:github-profile] %s [%s](%s): %d public repos",
                    $json->type,
                    $json->name,
                    $json->html_url,
                    $json->public_repos
                )
            );
        });
    }

    /*
     * Example:
     *   !!github Room-11/Jeeves
     *
     * [tag:github-repo] [Room-11/Jeeves](https://github.com/Room-11/Jeeves) Chatbot attempt -
     *    - Watchers: 14, Forks: 15, Last Pushed: 2016-05-26T08:57:41Z
     */
    protected function repo(Command $command, string $path): Promise
    {
        return resolve(function() use ($command, $path) {
            list($user, $repo) = explode('/', $path, 2);

            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request('https://api.github.com/repos/' . urlencode($user) . '/' . urlencode($repo));

            if ($response->getStatus() !== 200) {
                return $this->chatClient->postMessage($command, "Failed fetching repo for $path");
            }

            $json = json_decode($response->getBody());
            if (!isset($json->id)) {
                return $this->chatClient->postMessage($command, "Unknown repo $path");
            }

            return $this->chatClient->postMessage(
                $command,
                sprintf(
                    "[tag:github-repo] [%s](%s) %s - Watchers: %d, Forks: %d, Last Push: %s",
                    $json->full_name,
                    $json->html_url,
                    $json->description,
                    $json->watchers,
                    $json->forks,
                    $json->pushed_at
                )
            );
        });
    }

    public function getDescription(): string
    {
        return 'Displays Github status, profile, or repo information';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint('Github', [$this, 'github'], 'github'),
        ];
    }
}
