<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client;
use Room11\StackChat\Client\MessageResolver;

class Lmgtfy extends BasePlugin
{
    private const URL = 'http://lmgtfy.com/';
    private const USAGE = /** @lang text */ 'Usage: `!!lmgtfy [ <text> ]` / `!!lmgtfy [ <message URL> ]`';

    private $chatClient;
    private $messageResolver;

    public function __construct(Client $chatClient, MessageResolver $messageResolver)
    {
        $this->messageResolver = $messageResolver;
        $this->chatClient = $chatClient;
    }

    public function lmgtfy(Command $command)
    {
        $text = $command->getText();

        if ((bool) preg_match('~^http://chat\.stackoverflow\.com/transcript/message/(\d+)(#\d+)?$~', $text)) {
            $text = yield $this->messageResolver->resolveMessageText($command->getRoom(), $text);
        }
        
        return $this->chatClient->postReply(
            $command, $this->getResponse($text)
        );
    }

    private function getResponse(string $text = null): string
    {
        if (empty($text)) {
            return self::USAGE;
        }

        if (mb_strlen($text, "UTF-8") > 50) {
            return 'That string is too large, sorry!';
        }

        return self::URL . '?q=' . urlencode($this->removePings($text));
    }

    private function removePings(string $text): string 
    {
        return preg_replace('/(?:^|\s)(@[^\s]+)(?:$|\s)/', '', $text);
    }

    public function getName(): string
    {
        return 'lmgtfy';
    }

    public function getDescription(): string
    {
        return 'Generates a Let Me Google That For You URL';
    }

    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Lmgtfy', [$this, 'lmgtfy'], 'lmgtfy')];
    }
}

