<?php  declare(strict_types=1);
namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Exception;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\Jeeves\Chat\Client\PostFlags;
use function Amp\all;

class ReferenceNotFoundException extends Exception {}

class Changelog extends BasePlugin
{
    private const EXPRESSION = '~^[^/]+/[^/]+$~';
    private const BASE_URL = 'https://api.github.com';

    private $chatClient;
    private $httpClient;
    private $pluginData;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, KeyValueStore $pluginData)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->pluginData = $pluginData;
    }

    public function changelog(Command $command)
    {

        $repository = $command->getParameter(0) ?? 'Room-11/Jeeves';

        if (preg_match(self::EXPRESSION, $repository)) {
            return yield from $this->getLastCommit($command, $repository);
        }

        return $this->chatClient->postMessage($command, /** @lang text */ "Usage: !!changelog [ <profile>/<repo> <branch>]");
    }

    protected function getCommitReference(Command $command, string $path)
    {
        list($user, $repo) = explode('/', $path, 2);

        $branch = $command->getParameter(1) ?? 'master';
        $heads  = self::BASE_URL . '/repos/' . urlencode($user) . '/' . urlencode($repo) . '/git/refs/heads/';

        $promises = $this->httpClient->requestMulti([
            'heads'  => $heads,
            'branch' => $heads . urlencode($branch)
        ]);

        /** @var HttpResponse[] $responses */
        $responses = yield all($promises);

        if($responses['heads']->getStatus() !== 200){
            throw new ReferenceNotFoundException("Failed to fetch repository for $path. Typo?");
        }

        if($responses['branch']->getStatus() !== 200){
            throw new ReferenceNotFoundException("Failed to fetch branch $branch for $path. Typo?");
        }

        $commit = json_decode($responses['branch']->getBody(), true);

        if (!isset($commit['object']['sha'])) {
            throw new ReferenceNotFoundException("Failed to fetch the last commit reference for branch $branch of $path. Typo?");
        }

        return $commit['object']['sha'];
    }

    /**
     * Example:
     *   !!changelog Room-11/Jeeves <branch>
     *
     * @param Command $command
     * @param string $path
     * @return Promise
     */
    protected function getLastCommit(Command $command, string $path)
    {
        list($user, $repo) = explode('/', $path, 2);

        try {
            $sha = yield from $this->getCommitReference($command, $path);
        } catch (ReferenceNotFoundException $e) {
            return $this->chatClient->postMessage($command, $e->getMessage());
        }

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            self::BASE_URL . '/repos/'
            . urlencode($user) . '/'
            . urlencode($repo) . '/commits/'
            . $sha
        );

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command, "Failed to fetch last commit for $path");
        }

        $json = json_decode($response->getBody());
        if (!isset($json->html_url)) {
            return $this->chatClient->postMessage($command, "Unknown commit url for $path");
        }

        return $this->chatClient->postMessage(
            $command,
            sprintf(
                "[ [%s](%s) ] [ [%s](%s) ] %s - Committed by: %s on %s",
                $repo,
                "https://github.com/" . urlencode($user) . '/' . urlencode($repo),
                substr($sha, 0, 7),
                $json->html_url,
                $json->commit->message,
                $json->commit->author->name,
                (new \DateTimeImmutable($json->commit->author->date))->format('d.m.Y H:i')
            ),
            PostFlags::SINGLE_LINE
        );
    }

    public function getDescription(): string
    {
        return 'Displays latest commit for a given repository.';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint('Changelog', [$this, 'changelog'], 'changelog'),
        ];
    }

}

