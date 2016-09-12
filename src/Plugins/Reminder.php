<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\Chars;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\Storage\Admin as AdminStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Amp\resolve;
use function Amp\once;

class Reminder extends BasePlugin
{
    private $chatClient;
    private $storage;
    private $admin;

    const USAGE = "Usage: `!!reminder [ examples | list | <text> [ at <time> | in <delay> ] | unset <id> ]`";
    const REMINDER_REGEX = "/(.*)\s+(?:in|at)\s+(.*)/ui";

    public function __construct(ChatClient $chatClient, KeyValueStore $storage, AdminStore $admin) {
        $this->chatClient = $chatClient;
        $this->storage = $storage;
        $this->admin = $admin;
    }

    private function flush(Command $command) {
        return resolve(function() use($command) {
            if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            }

            $reminders = yield $this->storage->getKeys($command->getRoom());
            if ($reminders !== []) {
                foreach ($reminders as $key){
                    yield $this->storage->unset($key, $command->getRoom());
                }
            }
            return $this->chatClient->postMessage($command->getRoom(), "Reminders are gone.");
        });
    }

    private function unset(Command $command) {
        $messageId = $command->getParameter(1) ?? false;

        return resolve(function() use($command, $messageId) {
            if (!yield $this->admin->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            }

            if(!$messageId){ return $this->chatClient->postMessage($command->getRoom(), self::USAGE); }
            $key = ':'.$messageId;

            if (yield $this->storage->exists($key, $command->getRoom())) {
                yield $this->storage->unset($key, $command->getRoom());
                return $this->chatClient->postMessage($command->getRoom(), "Reminder unset.");
            }
            return $this->chatClient->postReply($command, "I'm sorry, I couldn't find that key.");
        });
    }

    public function showExamples(Command $command): Promise
    {
        $examples = "Examples: \n"
            . Chars::BULLET . " !!reminder foo at 18:00 \n"
            . Chars::BULLET . " With timezone: (ie. UTC-3) !!reminder foo at 18:00-3:00 \n"
            . Chars::BULLET . " !!reminder bar in 2 hours \n"
            . Chars::BULLET . " !!reminder unset jisy6 \n"
            . Chars::BULLET . " !!reminder list \n";

        return resolve(function () use($command, $examples) {
            return $this->chatClient->postMessage($command->getRoom(), $examples);
        });
    }

    private function getAllReminders(Command $command): Promise
    {
        return resolve(function() use($command) {
            $message = "Registered reminders are:";

            $reminders = yield $this->storage->getAll($command->getRoom());
            if($reminders === []){
                return $this->chatClient->postMessage($command->getRoom(), "There aren't any scheduled reminders.");
            }

            foreach ($reminders as $item => $value) {
                $text = $value['text'];
                $user = $value['username'];
                $key  = base_convert($value['id'], 10, 36);
                $timestamp = $value['timestamp'];

                if (!$timestamp) {
                    return $this->chatClient->postMessage($command->getRoom(), "Have a look at the time again, yo!");
                }
                $seconds = $timestamp - time();

                if ($seconds <= 0) {
                    return $this->chatClient->postReply($command, "I guess I'm late: " . $text);
                }

                $message .= sprintf(
                    "\n%s %s %s %s %s %s - %s - %s ",
                    Chars::BULLET,
                    $text,
                    Chars::RIGHTWARDS_ARROW,
                    "Id: " . $key,
                    Chars::RIGHTWARDS_ARROW,
                    date('l, dS F Y H:i (e)', $timestamp),
                    'Set by ' . $user,
                    'Seconds left: ' . $seconds
                );
            }

            return $this->chatClient->postMessage($command->getRoom(), $message);
        });
    }

    /**
     * Handle a command message
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

            $textOrCommand = $command->getParameter(0); // list or <text>
            if ( count(array_diff($command->getParameters(), array($textOrCommand))) < 1 ){
                switch ($textOrCommand){
                    case 'list': return yield $this->getAllReminders($command); break;
                    case 'examples': return yield $this->showExamples($command); break;
                    case 'flush': return yield $this->flush($command);
                }
            }

            if(
                $command->getParameter(0) === 'unset'
                && $command->getParameter(1) !== null
                && count($command->getParameters()) <= 2
            ){
                return yield $this->unset($command);
            }

            $parameters = implode(" ", $command->getParameters());

            if(!preg_match(self::REMINDER_REGEX, $parameters, $matches)){ // !!reminder Grab a beer! at 18:00
                return $this->chatClient->postMessage($command->getRoom(), self::USAGE);
            }

            $time = $matches[2] ?? false;
            $text = $matches[1] ?? false;
            if(!$time || !$text){
                return $this->chatClient->postMessage($command->getRoom(), self::USAGE);
            }

            $timestamp = strtotime($time) ?: strtotime("+{$time}"); // false|int
            if (!$timestamp) {
                return $this->chatClient->postMessage($command->getRoom(), "Have a look at the time again, yo!");
            }

            $messageId = $command->getId();
            $key   = ':' . base_convert($messageId, 10, 36);
            $value = [
                'id' => $messageId,
                'text' => $text,
                'delay' => $time,
                'username' => $command->getUserName(),
                'timestamp' => $timestamp
            ];

            $seconds = $timestamp - time();
            if ($seconds <= 0) {
                return $this->chatClient->postReply($command, "I guess I'm late: " . $text);
            }

            yield $this->storage->set($key, $value, $command->getRoom());

            once(function () use ($command, $value, $key) {
                yield $this->storage->unset($key, $command->getRoom());
                return $this->chatClient->postReply($command, $value['text']);
            }, $seconds * 1000);

            return $this->chatClient->postMessage($command->getRoom(), "Reminder set.");
        });
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
        return [new PluginCommandEndpoint('reminder', [$this, 'handleCommand'], 'reminder')];
    }
}

