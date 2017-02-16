<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Message\Message;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;

class JeevesDad extends BasePlugin
{
    const DEFAULT_GREET_FREQUENCY = 1000;
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

        if (random_int(1, yield from $this->getDadGreetFrequency($message->getRoom())) !== 1) {
            return;
        }

        $fullName = strtoupper(substr($match[1], 0, 1)) . substr($match[1], 1);

        $reply = sprintf('Hello %s. I am %s.', $fullName, $message->getRoom()->getSession()->getUser()->getName());

        if (preg_match('#^(\S+)\s+\S#', $fullName, $match)) {
            $reply .= sprintf(' Do you mind if I just call you %s?', $match[1]);
        }

        yield $this->chatClient->postReply($message, $reply);
    }

    private function isDadGreetEnabled(ChatRoom $room)
    {
        return (!yield $this->storage->exists('dadgreet', $room)) || (yield $this->storage->get('dadgreet', $room));
    }

    private function setDadGreetEnabled(ChatRoom $room, bool $enabled): \Generator
    {
        yield $this->storage->set('dadgreet', $enabled, $room);
    }

    private function getDadGreetFrequency(ChatRoom $room)
    {
        return (yield $this->storage->exists('dadgreet_frequency', $room))
            ? yield $this->storage->get('dadgreet_frequency', $room)
            : self::DEFAULT_GREET_FREQUENCY;
    }

    private function setDadGreetFrequency(ChatRoom $room, int $frequency): \Generator
    {
        yield $this->storage->set('dadgreet_frequency', $frequency, $room);
    }

    private function refreshGlobalJokesIfNecessary()
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

    private function getDadJoke(Command $command)
    {
        yield from $this->refreshGlobalJokesIfNecessary();

        $jokes = [];

        if (yield $this->storage->exists('jokes')) {
            $jokes = array_merge($jokes, yield $this->storage->get('jokes'));
        }

        if (yield $this->storage->exists('jokes', $command->getRoom())) {
            $jokes = array_merge($jokes, yield $this->storage->get('jokes', $command->getRoom()));
        }

        if (!$jokes) {
            return $this->chatClient->postReply($command, "Sorry, I can't remember any jokes right now :-(");
        }

        $joke = $jokes[array_rand($jokes)];

        return $this->chatClient->postMessage($command, sprintf('%s *%s*', $joke['setup'], $joke['punchline']));
    }

    private function addCustomDadJoke(Command $command)
    {
        static $expr = /** @lang regexp */ '#^(?<name>\w+)\s*/(?<setup>(?:[^\\\\/]++|\\\\\\\\|\\\\/|\\\\)++)/(?<punchline>.+)$#';

        if (!preg_match($expr, implode(' ', $command->getParameters(1)), $match)) {
            return $this->chatClient->postReply($command, "Sorry, I don't get that joke, I need `name / setup / punchline`");
        }

        $jokes = (yield $this->storage->exists('jokes', $command->getRoom()))
            ? yield $this->storage->get('jokes', $command->getRoom())
            : [];

        if (isset($jokes[$match['name']])) {
            return $this->chatClient->postReply($command, sprintf("I already know a joke about %s! Tell me a new one.", $match['name']));
        }

        $jokes[$match['name']] = ['setup' => trim($match['setup']), 'punchline' => trim($match['punchline'])];

        yield $this->storage->set('jokes', $jokes, $command->getRoom());

        return $this->chatClient->postReply($command, sprintf("Ha ha ha! Brilliant! I'll save that one about %s for later!", $match['name']));
    }

    private function removeCustomDadJoke(Command $command)
    {
        $name = $command->getParameter(1);

        if ($name === null) {
            return $this->chatClient->postReply($command, "You didn't tell me what to forget. Did you forget?");
        }

        $jokes = (yield $this->storage->exists('jokes', $command->getRoom()))
            ? yield $this->storage->get('jokes', $command->getRoom())
            : [];

        if (!isset($jokes[$name])) {
            return $this->chatClient->postReply($command, sprintf("I don't know a joke about %s to forget", $name));
        }

        unset($jokes[$name]);

        yield $this->storage->set('jokes', $jokes, $command->getRoom());

        return $this->chatClient->postReply($command, "Ever get the feeling you've forgotten something? I feel like that...");
    }

    private function listCustomDadJokes(Command $command)
    {
        $jokes = array_keys((yield $this->storage->exists('jokes', $command->getRoom()))
            ? yield $this->storage->get('jokes', $command->getRoom())
            : []
        );

        if (count($jokes) === 0) {
            $message = 'You haven\'t taught me any jokes yet but I always love to hear a good one';
        } else if (count($jokes) === 1) {
            $message = 'You guys have taught me a joke about ' . $jokes[0];
        } else {
            $last = array_pop($jokes);
            $message = 'You guys have taught me jokes about ' . implode(', ', $jokes) . ' and ' . $last;
        }

        return $this->chatClient->postReply($command, $message);
    }

    public function dadJoke(Command $command)
    {
        switch ($command->getParameter(0)) {
            case 'add': case 'learn':
                return yield from $this->addCustomDadJoke($command);

            case 'remove': case 'forget':
                return yield from $this->removeCustomDadJoke($command);

            case 'list': case 'show':
                return yield from $this->listCustomDadJokes($command);
        }

        return yield from $this->getDadJoke($command);
    }

    public function dadGreet(Command $command)
    {
        $room = $command->getRoom();

        switch ($command->getParameter(0)) {
            case 'on':
                if (!yield $this->admin->isAdmin($room, $command->getUserId())) {
                    return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
                }

                if (preg_match('#[0-9]+#', $command->getParameter(1))) {
                    if (1 > $frequency = (int)$command->getParameter(1)) {
                        return $this->chatClient->postReply($command, 'Frequency cannot be less than 1');
                    }

                    yield from $this->setDadGreetFrequency($room, $frequency);
                }

                yield from $this->setDadGreetEnabled($room, true);
                return $this->chatClient->postMessage($command, 'Dad greeting is now enabled with a frequency of ' . (yield from $this->getDadGreetFrequency($room)));

            case 'off':
                if (!yield $this->admin->isAdmin($room, $command->getUserId())) {
                    return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
                }

                yield from $this->setDadGreetEnabled($room, false);
                return $this->chatClient->postMessage($command, 'Dad greeting is now disabled');

            case 'status':
                $state = (yield from $this->isDadGreetEnabled($room))
                    ? 'enabled with a frequency of ' . (yield from $this->getDadGreetFrequency($room))
                    : 'disabled';

                return $this->chatClient->postMessage($command, 'Dad greeting is currently ' . $state);
        }

        return $this->chatClient->postReply($command, 'Syntax: ' . $command->getCommandName() . ' on|off|status [frequency]');
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
