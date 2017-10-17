<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\External\GithubIssue\Credentials;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Client\MessageResolver;

class Issue extends BasePlugin
{
    private const USAGE = /** @lang text */ 'Usage: `!!issue [<title> - <body>]`';
    private const CHAT_URL_EXP = '~^http://chat\.stackoverflow\.com/transcript/message/(\d+)(#\d+)?$~';
    private const PING_EXP = '/@([^\s]+)(?=$|\s)/';

    private $chatClient;
    private $messageResolver;
    private $httpClient;
    private $credentials;
    private $admin;

    public function __construct(
        ChatClient $chatClient,
        MessageResolver $messageResolver,
        HttpClient $httpClient,
        Credentials $credentials,
        AdminStorage $adminStorage
    ) {
        $this->credentials = $credentials;
        $this->httpClient = $httpClient;
        $this->messageResolver = $messageResolver;
        $this->chatClient = $chatClient;
        $this->admin = $adminStorage;
    }

    public function issue(Command $command)
    {
        if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
            return $this->chatClient->postReply($command, "Sorry, you're not cool enough to do that :(");
        }

        if (!$this->credentialsPresent()) {
            return $this->chatClient->postReply(
                $command, 'I\'m not configured to use GitHub :('
            );
        }

        $content = explode('-', $command->getCommandText(), 2);

        if (empty($content[0]) || count($content) > 2) {
            return $this->chatClient->postReply($command, self::USAGE);
        }

        $title = yield from $this->getTextFromCommand($content[0], $command);
        $body = null;

        if (isset($content[1])) {
            $body = yield from $this->getTextFromCommand($content[1], $command);
        }

        try {
            $response = yield from $this->createIssue($title, $body, $command->getId());
        } catch (\Throwable $e) {
            $response = $e->getMessage();
        }

        return $this->chatClient->postReply($command, $response);
    }

    private function createIssue(string $title, $body = '', int $id)
    {
        $requestBody = [
            'title' => $title,
            'body' => $body . "\n Source - http://chat.stackoverflow.com/transcript/message/$id#$id"
        ];

        $request = (new HttpRequest)
            ->setUri($this->credentials->getUrl())
            ->setMethod('POST')
            ->setBody(json_encode($requestBody))
            ->setAllHeaders($this->getAuthHeader());

        /** @var HttpResponse $result */
        $result = yield $this->httpClient->request($request);

        if ($result->getStatus() !== 201) {
            throw new \RuntimeException('I failed to create the issue :-(. You might want to create an issue about that');
        }

        $response = json_try_decode($result->getBody(), true);

        return "Issue created - {$response['html_url']}";
    }

    private function getAuthHeader(): array
    {
        $auth = 'Basic %s';

        if (empty($this->credentials->getToken())) {
            $credentials = base64_encode(
                $this->credentials->getUsername() . ':' . $this->credentials->getPassword()
            );
            return ['Authorization' => sprintf($auth, $credentials)];
        }

        return ['Authorization' => sprintf($auth, $this->credentials->getToken())];
    }

    private function credentialsPresent(): bool
    {
        if (!empty($this->credentials->getToken())) {
            return true;
        }

        if (empty($this->credentials->getUsername()) || empty($this->credentials->getPassword())) {
            return false;
        }

        return true;
    }

    private function getTextFromCommand($text, Command $command)
    {
        if (is_null($text)) {
            return null;
        }

        if (preg_match(self::CHAT_URL_EXP, $text)) {
            $text = yield from $this->messageResolver->resolveMessageText(
                $command->getRoom(), $text
            );
        }

        return yield from $this->replacePings($command, $text);
    }

    private function replacePings(Command $command, string $text)
    {
        $room = $command->getRoom();

        if (!preg_match_all(self::PING_EXP, $text, $matches)) {
            return $text;
        }

        $pingables = yield $this->chatClient->getPingableUserIDs($room, ...$matches[1]);

        $ids = array_values($pingables);
        $users = yield $this->chatClient->getMainSiteUsers($room, ...$ids);

        $pingableUsers = [];
        foreach ($pingables as $name => $id) {
            $pingableUsers[$name] = $users[$id];
        }

        return preg_replace_callback(self::PING_EXP, function($match) use($pingableUsers) {
            if (isset($pingableUsers[$match[1]]) && $pingableUsers[$match[1]]->getGithubUsername()) {
                return '@' . $pingableUsers[$match[1]]->getGithubUsername();
            }

            return $match[1];
        }, $text);
    }

    public function getName(): string
    {
        return 'issue';
    }

    public function getDescription(): string
    {
        return 'Creates an issue in a GitHub repository';
    }

    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Issue', [$this, 'issue'], 'issue')];
    }
}
