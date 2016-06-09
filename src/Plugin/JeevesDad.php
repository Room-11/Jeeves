<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Message\Message;
use Room11\Jeeves\Chat\PluginCommandEndpoint;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Plugin;
use Room11\Jeeves\Plugin\Traits\AutoName;
use Room11\Jeeves\Plugin\Traits\Helpless;
use Room11\Jeeves\Plugin\Traits\NoDisableEnable;
use Room11\Jeeves\Plugin\Traits\NoEventHandlers;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;

class JeevesDad implements Plugin
{
    use NoEventHandlers, NoDisableEnable, AutoName, Helpless;

    const FREQUENCY = 10;
    const JOKE_URL = 'http://niceonedad.com/assets/js/niceonedad.js';

    private $chatClient;
    private $httpClient;
    private $storage;
    private $admin;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, AdminStorage $admin, KeyValueStore $storage)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->admin = $admin;
        $this->storage = $storage;
    }

    public function handleMessage(Message $message)
    {
        if (!yield from $this->isDadGreetEnabled($message->getRoom())) {
            return;
        }

        if (!preg_match('#(?:^|\s)(?:i\'m|i am)\s+(.+?)\s*(?:[.,!]|$)#i', $message->getText(), $match)) {
            return;
        }

        if (random_int(1, self::FREQUENCY) !== 1) {
            return;
        }

        $fullName = strtoupper(substr($match[1], 0, 1)) . substr($match[1], 1);

        $reply = sprintf('Hello %s. I am %s.', $fullName, $message->getRoom()->getSessionInfo()->getUser()->getName());

        if (preg_match('#^(\S+)\s+\S#', $fullName, $match)) {
            $reply .= sprintf(' Do you mind if I just call you %s?', $match[1]);
        }

        yield $this->chatClient->postReply($message, $reply);
    }

    private function isDadGreetEnabled(ChatRoom $room): \Generator
    {
        return (!yield $this->storage->exists('dadgreet', $room)) || (yield $this->storage->get('dadgreet', $room));
    }

    private function refreshJokes(): \Generator
    {
        $refreshNeeded = (!yield $this->storage->exists('jokes'))
            || (!yield $this->storage->exists('refreshtime'))
            || (yield $this->storage->get('refreshtime')) < time();

        if (!$refreshNeeded) {
            return;
        }

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(self::JOKE_URL);

        if (!preg_match('#JOKES=(\[[^\]]+])#', $response->getBody(), $match)) {
            return;
        }

        $jokesJson = preg_replace('#((?<={)setup|(?<=,)punchline)#', '"$1"', $match[1]);
        $jokesJson = preg_replace_callback('#(?<=:|,|{)\'(.*?)\'(?=:|,|\))#', function($match) { return json_encode($match[1]); }, $jokesJson);

        yield $this->storage->set('jokes', json_try_decode($jokesJson, true));
        yield $this->storage->set('refreshtime', time() + 86400);
    }

    public function dadJoke(Command $command): \Generator
    {
        yield from $this->refreshJokes();

        $jokes = yield $this->storage->get('jokes');
        $joke = $jokes[array_rand($jokes)];

        return $this->chatClient->postMessage($command->getRoom(), sprintf('%s *%s*', $joke['setup'], $joke['punchline']));
    }

    public function dadGreet(Command $command): \Generator
    {
        switch (strtolower($command->getParameter(0))) {
            case 'on':
            case 'off':
                if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
                    return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
                }

                $enable = strtolower($command->getParameter(0)) === 'on';
                $state = $enable ? 'enabled' : 'disabled';

                yield $this->storage->set('dadgreet', $enable, $command->getRoom());
                return $this->chatClient->postMessage($command->getRoom(), 'Dad greeting is now ' . $state);

            case 'status':
                $state = (yield from $this->isDadGreetEnabled($command->getRoom())) ? 'enabled' : 'disabled';
                return $this->chatClient->postMessage($command->getRoom(), 'Dad greeting is currently ' . $state);
        }

        return $this->chatClient->postReply($command, 'Syntax: ' . $command->getCommandName() . ' on|off|status');
    }

    public function getDescription(): string
    {
        return 'Jokes, fresh from the mind of Jeeves\' dad';
    }

    /**
     * @return callable|null
     */
    public function getMessageHandler() /* : ?callable */
    {
        return [$this, 'handleMessage'];
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint('DadJoke', [$this, 'dadJoke'], 'dad', 'Get a random dad joke'),
            new PluginCommandEndpoint('DadGreet', [$this, 'dadGreet'], 'dadgreet', 'Turn the dad greeting on or off'),
        ];
    }
}
