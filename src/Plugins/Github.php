<?php  declare(strict_types=1);
namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Room11\StackChat\Client\Client;
use Room11\StackChat\Client\ChatRoomContainer;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Room\Room as ChatRoom;
use function Amp\repeat;

class Github extends BasePlugin
{
    private $chatClient;
    private $httpClient;
    private $pluginData;
    private $lastKnownStatus = null;

    public function __construct(Client $chatClient, HttpClient $httpClient, KeyValueStore $pluginData)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->pluginData = $pluginData;
    }

    public function enableForRoom(ChatRoom $room, bool $persist = true)
    {
        repeat(
            function() use ($room) { return $this->checkStatusChange($room); }, 150000
        );
    }

    public function github(Command $command)
    {
        $obj = $command->getParameter(0) ?? 'status';

        if ($obj === 'status') {
            return yield from $this->status($command);
        } elseif (strpos($obj, '/') === false) {
            return yield from $this->profile($command, $obj);
        } elseif (strpos($obj, '/') === strrpos($obj, '/')) {
            return yield from $this->repo($command, $obj);
        }

        return $this->chatClient->postMessage($command,
            /** @lang text */ "Usage: !!github [status | <project> | <profile> | <profile>/<repo> ]");
    }

    /**
     * Example:
     *   !!github
     *   !!github status
     *
     * [tag:github-status] good: Everything operating normally. as of 2016-05-25T18:44:58Z
     *
     * @param Command $command
     * @return Promise
     */
    protected function status(Command $command)
    {
        return $this->postResponse($command, yield from $this->getGithubStatus());
    }

    private function checkStatusChange(ChatRoom $room)
    {
        $response = yield from $this->getGithubStatus();

        if (!$response) {
            return null;
        }

        if (is_null($this->lastKnownStatus)) {
            $this->lastKnownStatus = $response->status;
            return null;
        }

        if ($this->lastKnownStatus === $response->status) {
            return null;
        }

        $this->lastKnownStatus = $response->status;
        return $this->postResponse($room, $response);
    }

    /**
     * @param ChatRoom|ChatRoomContainer $target
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

    private function getGithubStatus()
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request('https://status.github.com/api/last-message.json');

        if ($response->getStatus() !== 200) {
            return false;
        }

        return json_decode($response->getBody());
    }

    /**
     * Example:
     *   !!github Room-11
     *
     * [tag:github-profile] Organization [Room-11](https://github.com/Room-11): 15 public repos
     *
     * @param Command $command
     * @param string $profile
     * @return Promise
     */
    protected function profile(Command $command, string $profile)
    {
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
    }

    /**
     * Example:
     *   !!github Room-11/Jeeves
     *
     * [tag:github-repo] [Room-11/Jeeves](https://github.com/Room-11/Jeeves) Chatbot attempt -
     *    - Watchers: 14, Forks: 15, Last Pushed: 2016-05-26T08:57:41Z
     *
     * @param Command $command
     * @param string $path
     * @return Promise
     */
    protected function repo(Command $command, string $path)
    {
        list($user, $repo) = explode('/', $path, 2);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request('https://api.github.com/repos/' . urlencode($user).'/'.urlencode($repo));
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
