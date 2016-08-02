<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltInCommands;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\System\BuiltInCommand;
use const Room11\Jeeves\PROCESS_START_TIME;

class Uptime implements BuiltInCommand
{
    private $chatClient;
    private $startTime;

    private function makeDurationString(): string
    {
        $diff = (new \DateTime)->diff($this->startTime);
        $values = [
            'year'   => $diff->y,
            'month'  => $diff->m,
            'day'    => $diff->d,
            'hour'   => $diff->h,
            'minute' => $diff->i,
            'second' => $diff->s,
        ];

        $duration = [];

        foreach ($values as $unit => $value) {
            if ($value) {
                $duration[] = sprintf('%d %s%s', $value, $unit, $value === 1 ? '' : 's');
            }
        }

        $last = array_pop($duration);

        return $duration ?
            implode(', ', $duration) . ' and ' . $last
            : $last;
    }

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
        $this->startTime = new \DateTimeImmutable('@' . PROCESS_START_TIME);
    }

    /**
     * Handle a command message
     *
     * @param CommandMessage $command
     * @return Promise
     */
    public function handleCommand(CommandMessage $command): Promise
    {
        return $this->chatClient->postReply($command, sprintf(
            'I have been running for %s, since %s',
            $this->makeDurationString(),
            $this->startTime->format('Y-m-d H:i:s')
        ));
    }

    /**
     * Get a list of specific commands handled by this built-in
     *
     * @return string[]
     */
    public function getCommandNames(): array
    {
        return ['uptime'];
    }
}
