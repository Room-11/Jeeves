<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\MessageResolver;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\System\PluginCommandEndpoint;

class Goochle extends BasePlugin
{
    // From https://en.wikipedia.org/wiki/List_of_English_prepositions
    private const PREPOSITIONS = [
        'a',
        'an',
        'aboard',
        'about',
        '\'bout',
        'bout',
        'above',
        'abreast',
        'abroad',
        'across',
        'adjacent',
        'after',
        'against',
        '\'gainst',
        'along',
        '\'long',
        'alongside',
        'amid',
        'amidst',
        'among',
        'amongst',
        'apropos',
        'around',
        '\'round',
        'as',
        'astride',
        'at',
        '@',
        'atop',
        'ontop',
        'bar',
        'before',
        'B4',
        'behind',
        'below',
        'beneath',
        '\'neath',
        'beside',
        'besides',
        'between',
        'beyond',
        'but',
        'by',
        'circa',
        'come',
        'despite',
        'down',
        'during',
        'except',
        'for',
        '4',
        'from',
        'in',
        'inside',
        'into',
        'less',
        'like',
        'minus',
        'near',
        'nearer',
        'nearest',
        'notwithstanding',
        'of',
        'off',
        'on',
        'onto',
        'opposite',
        'out',
        'outside',
        'over',
        'past',
        'per',
        'post',
        'pre',
        'pro',
        'sans',
        'save',
        'short',
        'since',
        'than',
        'through',
        'thru',
        'throughout',
        'thruout',
        'to',
        '2',
        'toward',
        'towards',
        'under',
        'underneath',
        'unlike',
        'until',
        '\'til',
        'til',
        'till',
        'up',
        'upon',
        'upside',
        'versus',
        'vs.',
        'v.',
        'via',
        'vis-à-vis',
        'with',
        'w/',
        'within',
        'w/i',
        'without',
        'w/o',
        'worth',
        'according to',
        'adjacent to',
        'ahead of',
        'apart from',
        'as for',
        'as of',
        'as per',
        'as regards',
        'aside from',
        'astern of',
        'back to',
        'because of',
        'close to',
        'due to',
        'except for',
        'far from',
        'inside of',
        'instead of',
        'left of',
        'near to',
        'next to',
        'opposite of',
        'opposite to',
        'out from',
        'out of',
        'outside of',
        'owing to',
        'prior to',
        'pursuant to',
        'rather than',
        'regardless of',
        'right of',
        'subsequent to',
        'such as',
        'thanks to',
        'up to',
        'as far as',
        'as opposed to',
        'as soon as',
        'as well as',
        'at the behest of',
        'by means of',
        'by virtue of',
        'for the sake of',
        'in accordance with',
        'in addition to',
        'in case of',
        'in front of',
        'in lieu of',
        'in order to',
        'in place of',
        'in point of',
        'in spite of',
        'on account of',
        'on behalf of',
        'on top of',
        'with regard to',
        'with respect to',
        'with a view to',
    ];

    private $chatClient;
    private $messageResolver;
    private $prepositionRegex;

    public function __construct(ChatClient $chatClient, MessageResolver $messageResolver)
    {
        $this->chatClient = $chatClient;
        $this->prepositionRegex = $this->createPrepositionRegex();
        $this->messageResolver = $messageResolver;
    }

    public function trenslete(CommandMessage $command)
    {
        $text = yield $this->messageResolver->resolveMessageText($command->getRoom(), $command->getText());
        return $this->chatClient->postReply($command, $this->ruinPrepositions($text));
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Trenslete', [$this, 'trenslete'], 'goochle')];
    }

    public function getDescription(): string
    {
        return $this->ruinPrepositions('Trensletes text by ruining your prepositions.');
    }

    private function createPrepositionRegex(): string
    {
        $sorted = self::PREPOSITIONS;
        usort($sorted, function ($a, $b) { return strlen($b) <=> strlen($a); });

        $escaped = array_map('preg_quote', $sorted, array_fill(0, count($sorted), '/'));

        return '/\b(?:' . implode('|', $escaped) . ')\b/ui';
    }

    // Idea ripped off from The Kingdom of Loathing's "sword behind inappropriate prepositions."
    private function ruinPrepositions(string $input): string
    {
        $split = preg_split($this->prepositionRegex, $input);

        $output = '';
        for ($i = 0, $end = count($split) - 1; $i < $end; $i++) {
            // random_int() is used in place of array_rand() for cryptographically secure prepositions.
            $output .= $split[$i] . self::PREPOSITIONS[random_int(0, count(self::PREPOSITIONS) - 1)];
        }
        $output .= $split[$end];

        return $output;
    }
}
