<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\CommandOnly;
use Room11\Jeeves\Chat\PluginCommandEndpoint;

class Should implements Plugin
{
    use CommandOnly;

    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function should(Command $command): Promise
    {
        if (preg_match('~(\S+?) (.*?) or (.*?)(?:\?|$)~i', implode(" ", $command->getParameters()), $match)) {
            $answer = random_int(0, 1) ? $match[2] : $match[3];
            $person = strtolower($match[1]) === "i" ? "You" : "@{$match[1]}";
            $reply = "{$person} should {$answer}.";

            return $this->chatClient->postMessage($command->getRoom(), $reply);
        }

        return $this->chatClient->postReply($command, "Dunno.");
    }

    public function getName(): string
    {
        return 'Should';
    }

    public function getDescription(): string
    {
        return 'Should I write a description or rather not?';
    }

    public function getHelpText(array $args): string
    {
        // TODO: Implement getHelpText() method.
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Should', [$this, 'should'], 'should')];
    }
}
