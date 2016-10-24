<?php  declare(strict_types=1);
namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\Jeeves\Chat\Client\PostFlags;

class Changelog extends BasePlugin
{
    const EXPRESSION = '~^[^/]+/[^/]+$~';
    const BASE_URL = 'https://api.github.com';

    private $chatClient;
    private $httpClient;
    private $pluginData;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, KeyValueStore $pluginData)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->pluginData = $pluginData;
    }

    public function changelog(Command $command): \Generator {

        $repository = $command->getParameter(0) ?? 'Room-11/Jeeves';

        if (preg_match(self::EXPRESSION, $repository)) {
            return yield from $this->getLastCommit($command, $repository);
        }

        return $this->chatClient->postMessage($command->getRoom(), "Usage: !!changelog [ <profile>/<repo> ]");
    }

    protected function getCommitReference(Command $command, string $path)
    {
        list($user, $repo) = explode('/', $path, 2);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            self::BASE_URL . '/repos/'
            . urlencode($user) . '/'
            . urlencode($repo) . '/git/refs/heads/master'
        );

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command->getRoom(), "Failed to fetch repo for $path");
        }

        $json = json_decode($response->getBody());
        if (!isset($json->object->sha)) {
            return $this->chatClient->postMessage($command->getRoom(), "Failed to fetch reference SHA for $path");
        }

        return $json->object->sha;
    }

    /**
     * Example:
     *   !!changelog Room-11/Jeeves
     *
     * @param Command $command
     * @param string $path
     * @return \Generator
     */
    protected function getLastCommit(Command $command, string $path): \Generator
    {
        list($user, $repo) = explode('/', $path, 2);

        $sha = yield from $this->getCommitReference($command, $path);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            self::BASE_URL . '/repos/'
            . urlencode($user) . '/'
            . urlencode($repo) . '/commits/'
            . $sha
        );

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command->getRoom(), "Failed to fetch last commit for $path");
        }

        $json = json_decode($response->getBody());
        if (!isset($json->html_url)) {
            return $this->chatClient->postMessage($command->getRoom(), "Unknown commit url for $path");
        }

        return $this->chatClient->postMessage(
            $command->getRoom(),
            sprintf(
                "[ [%s](%s) ] [ [%s](%s) ] %s - Commited by: %s on %s",
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

