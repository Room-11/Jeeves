<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use IntervalParser\IntervalParser;
use Room11\Jeeves\Chat\Client\Chars;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\PostFlags;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\Admin as AdminStore;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Amp\cancel;
use function Amp\once;
use function Amp\resolve;

class Reminder extends BasePlugin
{
    private $chatClient;
    private $storage;
    private $watchers;
    private $admin;

    const USAGE = /** @lang text */  "Usage: `!!reminder [ examples | list | <text> [ at <time> | in <delay> ] | unset <id> ]` Try `!!reminder examples`" ;
    const REMINDER_REGEX = '/(.*)\s+(?:in|at)\s+(.*)/ui';
    const TIME_FORMAT_REGEX = /** @lang regexp */ '/(?<time>(?:\d|[01]\d|2[0-3]):[0-5]\d)[+-]?(?&time)?/ui';

    public function __construct(ChatClient $chatClient, KeyValueStore $storage, AdminStore $admin, array $watchers = []) {
        $this->chatClient = $chatClient;
        $this->watchers = $watchers;
        $this->storage = $storage;
        $this->admin = $admin;
    }

    private function nuke(Command $command) {
        return resolve(function() use($command) {
            if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            }

            $reminders = yield $this->storage->getKeys($command->getRoom());
            if ($reminders) {
                foreach ($reminders as $key){
                    $key = (string) $key;
                    yield $this->storage->unset($key, $command->getRoom());
                }
            }
            return $this->chatClient->postMessage($command->getRoom(), "Reminders are gone.");
        });
    }

    private function unset(Command $command) {
        $messageId = (string) $command->getParameter(1);

        return resolve(function() use($command, $messageId) {
            if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            }

            if (yield $this->storage->exists($messageId, $command->getRoom())) {
                yield $this->storage->unset($messageId, $command->getRoom());
                return $this->chatClient->postMessage($command->getRoom(), "Reminder $messageId was unset.");
            }

            return $this->chatClient->postReply($command, "I'm sorry, I couldn't find that key.");
        });
    }

    private function setReminder(Command $command, string $commandName): Promise
    {
        return resolve(function() use($command, $commandName) {

            $intervalParser = new IntervalParser();

            switch ($commandName){
                case 'in':
                    $parameters = $intervalParser->normalizeTimeInterval(implode(" ", $command->getParameters()));

                    $expression = IntervalParser::$intervalSeparatorDefinitions . IntervalParser::$intervalWithTrailingData;

                    if(preg_match($expression, $parameters, $matches)){
                        $time = $matches['interval'] ?? false;
                        $text = $matches['trailing'] ?? false;
                    }
                    break;
                case 'at':
                    $time = $command->getParameter(0) ?? false; // 24hrs

                    if($time && preg_match(self::TIME_FORMAT_REGEX, $time)){ // maybe @TODO support !!at monday next week remind?
                        $text = implode(" ", array_diff($command->getParameters(), array($time)));
                    }

                    break;
                case 'reminder':
                    $parameters = implode(" ", $command->getParameters());

                    if(!preg_match(self::REMINDER_REGEX, $parameters, $matches)){
                        return $this->chatClient->postMessage($command->getRoom(), self::USAGE);
                    }

                    $time = $matches[2] ?? '';
                    $text = $matches[1] ?? false;

                    if ($time !== '') {
                        $time = $intervalParser->normalizeTimeInterval($time);
                    }

                    break;
            }

            if(!isset($time) || !$time) return $this->chatClient->postMessage($command->getRoom(), "Have a look at the time again, yo!");
            if(!isset($text) || !$text) return $this->chatClient->postMessage($command->getRoom(), self::USAGE);

            $timestamp = strtotime($time) ?: strtotime("+{$time}"); // false|int

            if (!$timestamp) return $this->chatClient->postMessage($command->getRoom(), "Have a look at the time again, yo!");

            $key = (string) $command->getId();
            $value = [
                'id' => $key,
                'text' => $text,
                'delay' => $time,
                'userId' => $command->getUserId(),
                'username' => $command->getUserName(),
                'timestamp' => $timestamp
            ];

            $seconds = $timestamp - time();
            if ($seconds <= 0) return $this->chatClient->postReply($command, "I guess I'm late: " . $text);

            if(yield $this->storage->set($key, $value, $command->getRoom())){

                $watcher = once(function () use ($command, $value, $key) {
                    yield $this->storage->unset($key, $command->getRoom());
                    return $this->chatClient->postReply($command, $value['text']);
                }, $seconds * 1000);

                $this->watchers[] = $watcher;
                return $this->chatClient->postMessage($command->getRoom(), "Reminder $key is set.");
            }

            return $this->chatClient->postMessage($command->getRoom(), "Dunno what happened but I couldn't set the reminder.");
        });
    }

    private function getAllReminders(Command $command): Promise
    {
        return resolve(function() use($command) {
            $message = "Registered reminders are:";

            $reminders = yield $this->storage->getAll($command->getRoom());
            if(!$reminders){
                return $this->chatClient->postMessage($command->getRoom(), "There aren't any scheduled reminders.");
            }

            $timeouts = [];
            foreach ($reminders as $key => $value) {
                $text = $value['text'];
                $user = $value['username'];
                $timestamp = $value['timestamp'];
                $seconds = $timestamp - time();

                if ($seconds <= 0) {
                    $timeouts[] = $key;
                    continue;
                }

                $message .= sprintf(
                    "\n%s %s %s %s %s %s - %s - %s ",
                    Chars::BULLET,
                    $text,
                    Chars::RIGHTWARDS_ARROW,
                    "Id: :" . $key,
                    Chars::RIGHTWARDS_ARROW,
                    date('l, dS F Y H:i (e)', $timestamp),
                    'Set by ' . $user,
                    'Seconds left: ' . $seconds
                );
            }

            return count($timeouts) !== count($reminders)
                ? $this->chatClient->postMessage($command->getRoom(), $message)
                : null;
        });
    }

    /**
     * Handle a command message
     *
     * According to http://www.strawpoll.me/11212318/r this plugin will support the following commands
     * !!<reminder|remind> remind this <in|at> <time>
     * !!at <time> remind this
     * !!in <time> remind this
     *
     * @param Command $command
     * @return Promise
     */
    public function handleCommand(Command $command): Promise
    {
        return resolve(function() use($command) {

            $commandName = $command->getCommandName(); // <reminder|in|at>

            if ($command->hasParameters() === false && $commandName !== 'reminders') {
                return $this->chatClient->postMessage($command->getRoom(), self::USAGE);
            }

            /* $command->getParameter(0) can be: list | examples | flush | unset | <text> | <time> */
            $textOrCommand = $command->getParameter(0);

            switch ($commandName){
                case 'in':
                    return yield $this->setReminder($command, 'in');
                case 'at':
                    return yield $this->setReminder($command, 'at');
                case 'reminders':
                    return yield $this->getAllReminders($command);
                case 'reminder':
                    break;
            }

            if (count(array_diff($command->getParameters(), array($textOrCommand))) < 1){
                switch ($textOrCommand){
                    case 'list':
                        return yield $this->getAllReminders($command);
                    case 'examples':
                        return yield $this->getExamples($command);
                    case 'nuke': // nukes all reminders
                        return yield $this->nuke($command);
                }
            }

            if( $command->getParameter(0) === 'unset'
                && $command->getParameter(1) !== null
                && count($command->getParameters()) <= 2
            ){ return yield $this->unset($command); }

            return yield $this->setReminder($command, 'reminder');
        });
    }

    public function apologizeForExpiredReminders(ChatRoom $room, array $reminders)
    {
        if(!$reminders) return;

        foreach ($reminders as $key) {
            $key = (string) $key;
            $value = yield $this->storage->get($key, $room);
            $text = $value['text'];
            $name = $value['username'];
            $stamp = $value['timestamp'];
            $seconds = $stamp - time();

            if($seconds > 0) continue;

            if (null !== $pingableName = yield $this->chatClient->getPingableName($room, $name)) {
                $target = "@{$pingableName}";
                $reply = $target . " I guess I'm late: " . $text;

                $this->watchers[] = once(function () use ($room, $key, $reply) {
                    yield $this->storage->unset($key, $room);
                    return $this->chatClient->postMessage($room, $reply, PostFlags::ALLOW_PINGS);
                }, 1000);
            }
        }
    }

    public function rescheduleUpcomingReminders(ChatRoom $room, array $reminders)
    {
        if(!$reminders) return;

        $this->watchers = [];

        foreach ($reminders as $key){
            $key = (string) $key;
            $value = yield $this->storage->get($key, $room);
            $text  = $value['text'];
            $name  = $value['username'];
            $stamp = $value['timestamp'];
            $seconds = $stamp - time();

            if ($seconds <= 0) continue;

            if (null !== $pingableName = yield $this->chatClient->getPingableName($room, $name)) {
                $target = "@{$pingableName}";
                $reply = $target . " " . $text;

                $this->watchers[] = once(function () use ($room, $key, $reply) {
                    yield $this->storage->unset($key, $room);
                    return $this->chatClient->postMessage($room, $reply, PostFlags::ALLOW_PINGS);
                }, $seconds * 1000);
            }
        }
    }

    public function enableForRoom(ChatRoom $room, bool $persist = true){
        $reminders = yield $this->storage->getKeys($room);
        yield from $this->rescheduleUpcomingReminders($room, $reminders);
        yield from $this->apologizeForExpiredReminders($room, $reminders);
    }

    public function disableForRoom(ChatRoom $room, bool $persist = false){
        if(!$this->watchers) return;

        foreach ($this->watchers as $key => $id){
            cancel($id);
        }
    }

    public function getName(): string
    {
        return 'Reminders';
    }

    public function getDescription(): string
    {
        return 'Get reminded by an elephpant because, why not?';
    }

    public function getExamples(Command $command): Promise
    {
        $examples = "Examples: \n"
            . Chars::BULLET . " !!reminder foo at 18:00 \n"
            . Chars::BULLET . " With timezone: (ie. UTC-3) !!reminder foo at 18:00-3:00 \n"
            . Chars::BULLET . " !!reminder bar in 2 hours \n"
            . Chars::BULLET . " !!reminder unset 32901146 \n"
            . Chars::BULLET . " !!reminder list or just !!reminders \n"
            . Chars::BULLET . " !!in 2 days 42 hours 42 minutes 42 seconds 42! \n"
            . Chars::BULLET . " !!at 22:00 Grab a beer!";

        return resolve(function () use($command, $examples) {
            return $this->chatClient->postMessage($command->getRoom(), $examples);
        });
    }

    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint('reminders', [$this, 'handleCommand'], 'reminders'),
            new PluginCommandEndpoint('reminder', [$this, 'handleCommand'], 'reminder'),
            new PluginCommandEndpoint('in', [$this, 'handleCommand'], 'in'),
            new PluginCommandEndpoint('at', [$this, 'handleCommand'], 'at')
        ];
    }
}

