<?php declare(strict_types=1);
  
namespace Room11\Jeeves\Plugins;

use Amp\Success;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Room11\Jeeves\External\GithubIssue\Credentials;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\Jeeves\Chat\Client\MessageResolver;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;

class Issue extends BasePlugin
{
    const USAGE = 'Usage: `!!issue [<title> - <body>]`';
    const ISSUE_URL = 'https://api.github.com/repos/JayPHP/Project-Template/issues';
    const CHAT_URL_EXP = '~^http://chat\.stackoverflow\.com/transcript/message/(\d+)(#\d+)?$~';
    const PING_EXP = '/@([^\s]+)(?=$|\s)/';
    const REQUIRED_AUTH = ['username', 'password', 'token'];

    private $chatClient;
    private $httpClient;
    private $credentials;
    private $response;

    public function __construct(
        ChatClient $chatClient, 
        MessageResolver $messageResolver,
        HttpClient $httpClient,
        Credentials $credentials
    ) {
        $this->credentials = $credentials;
        $this->httpClient = $httpClient;
        $this->messageResolver = $messageResolver;
        $this->chatClient = $chatClient;
    }

    public function issue(Command $command)
    {
        if (!$this->credentialsPresent()) {
            return $this->chatClient->postReply(
                $command, 'I\'m not configured to use GitHub :('
            );
        }

        $content = explode('-', $command->getText());

        if (empty($content[0]) || count($content) > 2) {
            return $this->chatClient->postReply($command, self::USAGE);
        }
        
        $title = yield from $this->getTextFromCommand($content[0], $command);
        $body = null;

        if (isset($content[1])) {
            $body = yield from $this->getTextFromCommand($content[1], $command);
        }

        yield from $this->createIssue($title, $body, $command->getId());

        return $this->chatClient->postReply($command, $this->response);
    }

    private function createIssue(string $title, $body = '', int $id)
    {
        $requestBody = [
            'title' => $title,
            'body' => $body . "\n Source - http://chat.stackoverflow.com/transcript/message/$id#$id"
        ];

        $username = $this->credentials->get('username');
        $password = $this->credentials->get('password');

        $request = (new HttpRequest)
            ->setUri(self::ISSUE_URL)
            ->setMethod('POST')
            ->setBody(json_encode($requestBody))
            ->setAllHeaders([
                'Authorization' => 'Basic ' . base64_encode("$username:$password"),
                'X-GitHub-OTP' => base64_encode($this->credentials->get('token'))
            ]);

        $result = yield $this->httpClient->request($request);

        if ($result->getStatus() !== 201) {
            $this->response = 'I failed to create the issue :-(. You might want to create an issue about that';
            return new Success();
        }

        $response = json_decode($result->getBody(), true);
        $this->response = "Issue created - {$response['html_url']}";
        return new Success();
    }

    private function credentialsPresent()
    {
        foreach (self::REQUIRED_AUTH as $key) {
            if (!$this->credentials->exists($key) || empty($this->credentials->get($key))) {
                return false;
            }
        }

        return true;
    }

    private function getTextFromCommand($text, $command)
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

    private function replacePings($command, string $text)
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

        return preg_replace_callback(self::PING_EXP, 
            function($match) use($pingableUsers) 
            {
                if (isset($pingableUsers[$match[1]])) {
                    return '@' . $pingableUsers[$match[1]]->getGithubUsername();
                }

                return $match[1];
            },
        $text);
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

