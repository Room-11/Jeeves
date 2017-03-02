<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use IntervalParser\IntervalParser;
use Room11\Jeeves\Chat\Client\Chars;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\PostFlags;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use function Room11\Jeeves\dateinterval_to_string;
use Room11\Jeeves\Exception;
use Room11\Jeeves\Storage\Admin as AdminStore;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Amp\cancel;
use function Amp\once;
use function Amp\resolve;

class InvalidReminderTextException extends Exception {}
class InvalidReminderTimeException extends Exception {}

class Reminder extends BasePlugin
{
    private const STRUCT_KEY_TEXT = 'text';
    private const STRUCT_KEY_DELAY = 'delay';
    private const STRUCT_KEY_TIMESTAMP = 'timestamp';
    private const STRUCT_KEY_ID = 'id';
    private const STRUCT_KEY_USER_ID = 'userId';
    private const STRUCT_KEY_USER_NAME = 'username';

    private const USAGE = /** @lang text */  "Usage: `!!reminder [ examples | list | <text> [ at <time> | in <delay> ] | unset <id> ]` Try `!!reminder examples`" ;
    private const REMINDER_REGEX = '/(.*)\s+(?:in|at)\s+(.*)/ui';
    private const TIME_FORMAT_REGEX = /** @lang regexp */ '/(?<time>(?:\d|[01]\d|2[0-3]):[0-5]\d)[+-]?(?&time)?/ui';

    private $chatClient;
    private $storage;
    private $intervalParser;
    private $adminStorage;

    private $watchers = [];

    public function __construct(
        ChatClient $chatClient,
        KeyValueStore $storage,
        AdminStore $adminStorage,
        IntervalParser $intervalParser
    ) {
        $this->chatClient = $chatClient;
        $this->storage = $storage;
        $this->adminStorage = $adminStorage;
        $this->intervalParser = $intervalParser;
    }

    private function nuke(Command $command)
    {
        if (!yield $this->adminStorage->isAdmin($command->getRoom(), $command->getUserId())) {
            return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
        }

        yield $this->storage->clear($command->getRoom());

        return $this->chatClient->postMessage($command, "Reminders are gone.");
    }

    private function unset(Command $command)
    {
        $messageId = (string) $command->getParameter(1);

        if (!yield $this->adminStorage->isAdmin($command->getRoom(), $command->getUserId())) {
            return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
        }

        if (!yield $this->storage->exists($messageId, $command->getRoom())) {
            return $this->chatClient->postReply($command, "I'm sorry, I couldn't find that key.");
        }

        yield $this->storage->unset($messageId, $command->getRoom());

        return $this->chatClient->postMessage($command, "Reminder $messageId was unset.");
    }

    private function parseCommandIntoTimeAndText(Command $command)
    {
        $time = $text = null;

        switch ($command->getCommandName()) {
            case 'in':
                $parameters = $this->intervalParser->normalizeTimeInterval(implode(" ", $command->getParameters()));

                $expression = IntervalParser::$intervalSeparatorDefinitions . IntervalParser::$intervalWithTrailingData;

                if (preg_match($expression, $parameters, $matches)){
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

            default:
                $parameters = implode(" ", $command->getParameters());

                if(!preg_match(self::REMINDER_REGEX, $parameters, $matches)){
                    return $this->chatClient->postMessage($command, self::USAGE);
                }

                $time = $matches[2] ?? '';
                $text = $matches[1] ?? false;

                if ($time !== '') {
                    $time = $this->intervalParser->normalizeTimeInterval($time);
                }

                break;
        }

        if (!isset($time) || !$time) {
            throw new InvalidReminderTimeException;
        }

        if (!isset($text) || !$text) {
            throw new InvalidReminderTextException;
        }

        $now = time();
        $timestamp = strtotime($time) ?: strtotime("+{$time}"); // false|int

        // If the string was just a time, we might have passed it. If so, move the target time 1 day ahead.
        if ($timestamp <= $now && preg_match(self::TIME_FORMAT_REGEX, $time) === 1) {
            $timestamp = strtotime("{$time} + 1 day");
        }

        if (!$timestamp || $timestamp <= $now) {
            throw new InvalidReminderTimeException;
        }

        return [
            self::STRUCT_KEY_TEXT      => $text,
            self::STRUCT_KEY_DELAY     => $time,
            self::STRUCT_KEY_TIMESTAMP => $timestamp,
        ];
    }

    private function unsetReminderAndPostMessage(string $key, $messageOrigin, string $message)
    {
        if ($messageOrigin instanceof Command) {
            $room = $messageOrigin->getRoom();
            $command = $messageOrigin;
        } else {
            $room = $messageOrigin;
            $command = null;
        }

        yield $this->storage->unset($key, $room);

        return isset($command)
            ? $this->chatClient->postReply($command, $message, PostFlags::ALLOW_PINGS)
            : $this->chatClient->postMessage($room, $message, PostFlags::ALLOW_PINGS);
    }

    private function setReminder(Command $command)
    {
        try {
            $value = $this->parseCommandIntoTimeAndText($command);
        } catch (InvalidReminderTimeException $e) {
            return $this->chatClient->postMessage($command, 'Have a look at the time again, yo!');
        } catch (InvalidReminderTextException $e) {
            return $this->chatClient->postMessage($command, self::USAGE);
        }

        $value[self::STRUCT_KEY_ID] = (string)$command->getId();
        $value[self::STRUCT_KEY_USER_ID] = $command->getUserId();
        $value[self::STRUCT_KEY_USER_NAME] = $command->getUserName();

        $seconds = $value[self::STRUCT_KEY_TIMESTAMP] - time();

        if ($seconds <= 0) {
            return $this->chatClient->postReply($command, "I guess I'm late: {$value[self::STRUCT_KEY_TEXT]}");
        }

        if (!yield $this->storage->set($value[self::STRUCT_KEY_ID], $value, $command->getRoom())){
            return $this->chatClient->postMessage($command, "Dunno what happened but I couldn't set the reminder.");
        }

        $this->watchers[] = once(function() use ($command, $value) {
            resolve($this->unsetReminderAndPostMessage($value[self::STRUCT_KEY_ID], $command, $value[self::STRUCT_KEY_TEXT]));
        }, $seconds * 1000);

        return $this->chatClient->postMessage($command, "Reminder {$value[self::STRUCT_KEY_ID]} is set.");
    }

    private function getAllReminders(Command $command)
    {
        $message = "Registered reminders are:";

        $reminders = yield $this->storage->getAll($command->getRoom());
        if (!$reminders) {
            return $this->chatClient->postMessage($command, "There aren't any scheduled reminders.");
        }

        $timeouts = [];

        foreach ($reminders as $key => $value) {
            $seconds = $value[self::STRUCT_KEY_TIMESTAMP] - time();

            if ($seconds <= 0) {
                $timeouts[] = $key;
                continue;
            }

            $message .= sprintf(
                "\n%s %s %s %s %s %s - %s - %s ",
                Chars::BULLET,
                $value[self::STRUCT_KEY_TEXT],
                Chars::RIGHTWARDS_ARROW,
                "Id: :" . $key,
                Chars::RIGHTWARDS_ARROW,
                date('l, dS F Y H:i (e)', $value[self::STRUCT_KEY_TIMESTAMP]),
                'Set by ' . $value[self::STRUCT_KEY_USER_NAME],
                'Time left: ' . dateinterval_to_string(new \DateInterval("PT{$seconds}S"))
            );
        }

        return count($timeouts) < count($reminders)
            ? $this->chatClient->postMessage($command, $message)
            : null;
    }

    private function apologizeForExpiredReminders(ChatRoom $room, array $reminders)
    {
        if (!$reminders) {
            return;
        }

        foreach ($reminders as $key) {
            $key = (string) $key;
            $value = yield $this->storage->get($key, $room);

            $name = $value[self::STRUCT_KEY_USER_NAME];
            $seconds = $value[self::STRUCT_KEY_TIMESTAMP] - time();

            if ($seconds > 0 || null === $pingableName = yield $this->chatClient->getPingableName($room, $name)) {
                continue;
            }

            $reply = "@{$pingableName} I guess I'm late: {$value[self::STRUCT_KEY_TEXT]}";

            $this->watchers[] = once(function () use ($key, $room, $reply) {
                resolve($this->unsetReminderAndPostMessage($key, $room, $reply));
            }, 1000);
        }
    }

    private function rescheduleUpcomingReminders(ChatRoom $room, array $reminders)
    {
        if (!$reminders) {
            return;
        }

        $this->watchers = [];

        foreach ($reminders as $key){
            $key = (string) $key;
            $value = yield $this->storage->get($key, $room);
            $text  = $value[self::STRUCT_KEY_TEXT];
            $name  = $value[self::STRUCT_KEY_USER_NAME];
            $stamp = $value[self::STRUCT_KEY_TIMESTAMP];
            $seconds = $stamp - time();

            if ($seconds <= 0) {
                continue;
            }

            if (null !== $pingableName = yield $this->chatClient->getPingableName($room, $name)) {
                $target = "@{$pingableName}";
                $reply = $target . " " . $text;

                $this->watchers[] = once(function () use ($key, $room, $reply) {
                    resolve($this->unsetReminderAndPostMessage($key, $room, $reply));
                }, $seconds * 1000);
            }
        }
    }

    private function getExamples(Command $command): Promise
    {
        $examples = "Examples: \n"
            . Chars::BULLET . " !!reminder foo at 18:00 \n"
            . Chars::BULLET . " With timezone: (ie. UTC-3) !!reminder foo at 18:00-3:00 \n"
            . Chars::BULLET . " !!reminder bar in 2 hours \n"
            . Chars::BULLET . " !!reminder unset 32901146 \n"
            . Chars::BULLET . " !!reminder list or just !!reminders \n"
            . Chars::BULLET . " !!in 2 days 42 hours 42 minutes 42 seconds 42! \n"
            . Chars::BULLET . " !!at 22:00 Grab a beer!";

        return $this->chatClient->postMessage($command, $examples);
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
        $commandName = $command->getCommandName(); // <reminder|in|at>

        if ($command->hasParameters() === false && $commandName !== 'reminders') {
            return $this->chatClient->postMessage($command, self::USAGE);
        }

        switch ($commandName) {
            case 'in':
            case 'at':
                return resolve($this->setReminder($command));

            case 'reminders':
                return resolve($this->getAllReminders($command));
        }

        $parameters = $command->getParameters();

        if (count($parameters) === 1) {
            switch ($parameters[0]){
                case 'list':
                    return resolve($this->getAllReminders($command));

                case 'examples':
                    return $this->getExamples($command);

                case 'nuke':
                    return resolve($this->nuke($command));
            }
        }

        if (count($parameters) === 2) {
            switch ($parameters[0]){
                case 'unset':
                    return resolve($this->unset($command));
            }
        }

        return $this->setReminder($command);
    }

    public function enableForRoom(ChatRoom $room, bool $persist = true)
    {
        $reminders = yield $this->storage->getKeys($room);

        yield from $this->rescheduleUpcomingReminders($room, $reminders);
        yield from $this->apologizeForExpiredReminders($room, $reminders);
    }

    public function disableForRoom(ChatRoom $room, bool $persist = false)
    {
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

