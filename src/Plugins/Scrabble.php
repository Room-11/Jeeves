<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;

class Scrabble extends BasePlugin
{
    const USAGE = "Usage: `!!scrabble [words to calculate score for]`";

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

    public function getDescription(): string
    {
        return 'Calculates the Scrabble score for the given input';
    }

    public function scrabble(Command $command): Promise
    {
        if ($command->hasParameters() === false) {
            return $this->chatClient->postMessage($command, self::USAGE);
        }

        return $this->chatClient->postReply(
            $command, 
            $this->formatScores(
               $this->calculateScores(
                    $this->getScorableWords(
                        $command->getParameters()
                    )
                )
            )
        );
    }
    
    private function getScorableWords(array $words): array
    {
        $chars = preg_quote(implode(static::SCORES['en']), '~');
        $words = preg_replace("~[^$chars]~i", '', $words);
        // @todo remove any non-dictionary words
        
        return array_filter($words, 'strlen');
    }

    private function calculateScores(array $words): array
    {
        $scores = [];
        foreach ($words as $word) {
            $scores[$word] = $this->calculateScore($word);
        }

        return $scores;
    }

    private function calculateScore(string $word): int
    {
        $sum = 0;
        for ($i = 0; $i < strlen($word); $i++) {
            $char = preg_quote($word[$i], '~');
            $sum += key(preg_grep("~$char~i", static::SCORES['en']));
        }

        return $sum;
    }

    private function formatScores(array $scores): string
    {
        $total = 0;
        $result = '';

        foreach ($scores as $word => $score) {
            $result .= "$word = $score, ";
            $total += $score;
        }

        return $result . "TOTAL SCORE = $total";
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Scrabble', [$this, 'scrabble'], 'scrabble')];
    }
}
