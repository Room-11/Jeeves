<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;
use SebastianBergmann\Version as SebastianVersion;

class Version implements Plugin
{
    const COMMAND = 'version';

    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function handle(Message $message): \Generator
    {
        if (!$this->validMessage($message)) {
            return;
        }

        yield from $this->getVersion();
    }

    private function validMessage(Message $message): bool
    {
        return $message instanceof Command
        && $message->getCommand() === self::COMMAND;
    }

    private function getVersion(): \Generator
    {
        $version = new SebastianVersion(VERSION, dirname(dirname(dirname(__DIR__))));

        $version = preg_replace_callback("@(v([0-9.]+)-(\d+))-g([0-9a-f]+)@", function($match) {
            return sprintf(
                "[%s-g%s](%s)",
                $match[1],
                $match[4],
                "https://github.com/Room-11/Jeeves/commit/" . $match[4]
            );
        }, $version->getVersion());

        yield from $this->chatClient->postMessage($version);
    }
}
