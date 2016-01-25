<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\Xhr as ChatClient;
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
        yield from $this->chatClient->postMessage($version->getVersion());
    }
}
