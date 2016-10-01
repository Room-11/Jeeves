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

    const USAGE = "Usage: `!!reminder [ examples | list | <text> [ at <time> | in <delay> ] | unset <id> ]` Try `!!reminder examples`" ;
    const REMINDER_REGEX = "/(.*)\s+(?:in|at)\s+(.*)/ui";
    const TIME_FORMAT_REGEX = "/(?<time>(?:\d|[01]\d|2[0-3]):[0-5]\d)[+-]?(?&time)?/ui";

    public function __construct(ChatClient $chatClient, KeyValueStore $storage, AdminStore $admin, array $watchers = []) {
        $this->chatClient = $chatClient;
        $this->watchers = $watchers;
        $this->storage = $storage;
        $this->admin = $admin;
    }

    private function nuke(Command $command) {
        return resolve(function() use($command) {
            if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "One cannot simply nuke the reminders without asking Dave.");
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
                return $this->chatClient->postReply($command, "Only an admin can unset a reminder.");
            }

            if (yield $this->storage->exists($messageId, $command->getRoom())) {
                yield $this->storage->unset($messageId, $command->getRoom());
                return $this->chatClient->postMessage($command->getRoom(), "Reminder unset.");
            }

            return $this->chatClient->postReply($command, "I'm sorry, I couldn't find that key.");
        });
    }

    private function startRandomConversation(string $target, string $message, string $setBy): string
    {
        $noGrumbles = $everyone = false;

        $last = substr($message, -1);
        $chars = [ '.', '!', '?' ];
        if(!in_array($last, $chars)) {
            $message .= '. ';
            $noGrumbles = true;
        }

        if ($target != "everyone") {
            $target = "@{$target}";
        } else {
            $target = 'o/ Everyone, ';
            $everyone = true;
        }

        $start = [
            ' wanted me to remind you ',
            ' asked me to remind you '
        ];

        $who = ("@{$setBy}" == $target) ? 'you' : $setBy;

        $grumble = [
            ' So get on that, would ya?',
            " It's about time you get on that.",
            " Let's not forget that."
        ];

        $message = (!$everyone) ? $target . ', earlier ' . $who . $start[array_rand($start)] . $message : $target . $message;

        if(!$noGrumbles) $message .= $grumble[array_rand($grumble)];

        return $message;
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
                    $temp = $matches[1] ?? false;
                    if(!$temp) break;
                    $tempIsMessage = false;

                    $textOrUser = $command->getParameter(0);
                    switch ($textOrUser){
                        case 'me':
                        case 'you':
                            $target = $command->getUserName();
                            break;
                        case 'everyone':
                            $target = "everyone";
                            break;
                        default:
                            if(preg_match("/^@(?<username>.*)/ui", $textOrUser, $matches)){
                                $target = $matches['username'];
                                break;
                            }

                            $target = $command->getUserName();
                            $tempIsMessage = true;
                            break;
                    }

                    $message = (!$tempIsMessage) ? substr($temp, strlen($textOrUser)) : $temp;

                    $actionOrFact = $command->getParameter(1);
                    $array = [ 'to', 'that', 'about' ];
                    if(in_array($actionOrFact, $array)){
                        $message = preg_replace("/{$actionOrFact}/", '', $message, 1);
                    }

                    $setBy = $command->getUserName();

                    if($setBy !== $target){
                       if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
                           return $this->chatClient->postReply($command, "Only an admin can set a reminder for someone else.");
                       }
                    }

                    $text = $this->startRandomConversation($target, trim($message), $setBy);

                    if ($time !== '') $time = $intervalParser->normalizeTimeInterval($time);

                    break;
            }

            $for = $textOrUser ?? '';

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
                'timestamp' => $timestamp,
                'for' => $for
            ];

            $seconds = $timestamp - time();
            if ($seconds <= 0) return $this->chatClient->postReply($command, "I guess I'm late: " . $text);

            if(yield $this->storage->set($key, $value, $command->getRoom())){

                $watcher = once(function () use ($command, $value, $key) {
                    yield $this->storage->unset($key, $command->getRoom());

                    if($value['for'] == "everyone"){
                        return $this->chatClient->postMessage($command->getRoom(), $value['text']);
                    } elseif ($value['for'] !== $value['username']){
                        return $this->chatClient->postMessage($command->getRoom(), $value['text'], PostFlags::ALLOW_PINGS);
                    }

                    return $this->chatClient->postReply($command, $value['text']);
                }, $seconds * 1000);

                $this->watchers[] = $watcher;
                return $this->chatClient->postMessage($command->getRoom(), "Reminder set.");
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

            if(count($timeouts) !== count($reminders)){
                return $this->chatClient->postMessage($command->getRoom(), $message);
            }
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

            if ($command->hasParameters() === false) {
                return $this->chatClient->postMessage($command->getRoom(), self::USAGE);
            }

            /* $command->getParameter(0) can be: list | examples | flush | unset | <text> | <time> */
            $textOrCommand = $command->getParameter(0);
            $commandName = $command->getCommandName(); // <reminder|in|at>

            switch ($commandName){
                case 'in':
                    return yield $this->setReminder($command, 'in');
                case 'at':
                    return yield $this->setReminder($command, 'at');
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

    public function apologizeForExpiredReminders(ChatRoom $room, array $reminders): \Generator
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

    public function rescheduleUpcomingReminders(ChatRoom $room, array $reminders): \Generator
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
            . Chars::BULLET . " !!reminder list \n"
            . Chars::BULLET . " !!remind me to grab a beer in 2 hours \n"
            . Chars::BULLET . " !!remind everyone that strpbrk is a thing... in 12 hours \n"
            . Chars::BULLET . " !!remind @anAdmin to unpin that last xkcd in 2 days\n"
            . Chars::BULLET . " !!in 2 days 42 hours 42 minutes 42 seconds 42! \n"
            . Chars::BULLET . " !!at 22:00 Grab a beer!";

        return resolve(function () use($command, $examples) {
            return $this->chatClient->postMessage($command->getRoom(), $examples);
        });
    }

    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint('reminder', [$this, 'handleCommand'], 'reminder'),
            new PluginCommandEndpoint('in', [$this, 'handleCommand'], 'in'),
            new PluginCommandEndpoint('at', [$this, 'handleCommand'], 'at')
        ];
    }
}

