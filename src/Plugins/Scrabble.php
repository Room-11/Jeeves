<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;

class Scrabble extends BasePlugin
{
    const SCORES = [
        'en' => [
            1 => 'EAIONRTLSU',
            2 => 'DG',
            3 => 'BCMP',
            4 => 'FHVWY',
            5 => 'K',
            8 => 'JX',
            10 => 'QZ'
        ]
    ];

    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function scrabble(Command $command): Promise
    {
        if (!$command->hasParameters()) {
            return new Success();
        }

        return $this->chatClient->postReply(
            $command, 
            $this->calculateScore(implode($command->getParameters()))
        );
    }

    public function getDescription(): string
    {
        return 'Calculates the Scrabble score for the given input';
    }

    private function calculateScore(string $input): string
    {
        $sum = 0;
        foreach ($input as $char) {
            $char = preg_quote($char, '~');
            $sum += key(preg_grep("~$char~i", SCORES['en']));
        }
        return $sum;
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Scrabble', [$this, 'scrabble'], 'scrabble')];
    }
}
