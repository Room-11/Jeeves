<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;

class Rebecca implements Plugin
{
    const COMMAND = 'rebecca';

    const VIDEO_URL = 'https://www.youtube.com/watch?v=kfVsfOSbJY0';

    private $chatClient;

    public function __construct(ChatClient $chatClient) {
        $this->chatClient = $chatClient;
    }

    public function handle(Message $message): \Generator {
        if (!$this->validMessage($message)) {
            return;
        }

        yield from $this->getResult($message);
    }

    private function validMessage(Message $message): bool {
        return $message instanceof Command
            && $message->getCommand() === self::COMMAND;
    }

    private function getResult(Message $message): \Generator {
        yield from $this->chatClient->postMessage(
            sprintf(
                ':%s %s',
                $message->getOrigin(),
                $this->getRebeccaLinkIfFriday()
            )
        );
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
                $timeLeft = $this->getTimeUntilNextFriday();

                return sprintf(
                    'Only %d days, %d hours and %d minutes left until Rebeccaday, OMG!',
                    $timeLeft->days,
                    $timeLeft->h,
                    $timeLeft->i
                );
        }
    }

    private function getTimeUntilNextFriday(): \DateInterval
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $friday = new \DateTime('next friday', new \DateTimeZone('UTC'));

        return $now->diff($friday);
    }
}
