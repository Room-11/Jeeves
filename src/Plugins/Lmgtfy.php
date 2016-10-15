<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\Jeeves\Chat\Client\MessageResolver;
use Room11\Jeeves\Chat\Room\Room;

class Lmgtfy extends BasePlugin
{
    const URL = 'http://lmgtfy.com/';
    const USAGE = 'Usage: `!!lmgtfy [ <text> ]` / `!!lmgtfy [ <message URL> ]`';

    private $chatClient;

    public function __construct(ChatClient $chatClient, MessageResolver $messageResolver)
    {
        $this->messageResolver = $messageResolver;
        $this->chatClient = $chatClient;
    }

    public function lmgtfy(Command $command)
    {
        $text = $command->getText();

        if ((bool) preg_match('~^http://chat\.stackoverflow\.com/transcript/message/(\d+)(#\d+)?$~', $text)) {
            $text = yield from $this->getRawMessage($command->getRoom(), $text);
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

    private function getRawMessage(Room $room, string $link): string
    {
        $ID = $this->messageResolver->resolveMessageIDFromPermalink($link);

        $messageInfo = yield $this->chatClient->getMessageHTML($room, $ID);
        $messageBody = html_entity_decode($messageInfo, ENT_QUOTES);

        return $messageBody;
    }

    private function removePings(string $text): string 
    {
        return preg_replace('/(?:^|\s)(@[^\s]+)(?:$|\s)/', '', $text);
    }

    public function getName(): string
    {
        return 'lmgtfy - Let Me Google That For You';
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
