<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;

class Rebecca implements Plugin
{
    use CommandOnlyPlugin;

    const VIDEO_URL = 'https://www.youtube.com/watch?v=kfVsfOSbJY0';

    private $chatClient;

    public function __construct(ChatClient $chatClient) {
        $this->chatClient = $chatClient;
    }

    private function getResult(Command $command): \Generator {
        yield from $this->chatClient->postReply($command, $this->getRebeccaLinkIfFriday());
    }

    private function getRebeccaLinkIfFriday(): string
    {
        switch (date('l')) {
        case 'Thursday':
            return "Happy Prebeccaday!";
        case 'Friday':
            return self::VIDEO_URL;
        case 'Saturday':
            return "Today is Saturday. And Sunday comes afterwards";
        default:
            return $this->getCountdown();
        }
    }

    private function getCountdown(): string
    {
        $timeLeft = $this->getTimeUntilNextFriday();

        return sprintf(
            'Only %d days, %d hours and %d minutes left until Rebeccaday, OMG!',
            $timeLeft->days,
            $timeLeft->h,
            $timeLeft->i
        );

    }

    private function getTimeUntilNextFriday(): \DateInterval
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $friday = new \DateTime('next friday', new \DateTimeZone('UTC'));

        return $now->diff($friday);
    }

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator
    {
        yield from $this->getResult($command);
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getHandledCommands(): array
    {
        return ['rebecca'];
    }
}
