<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Amp\Success;
use Room11\GoogleSearcher\Searcher as GoogleSearcher;
use Room11\GoogleSearcher\SearchFailedException;
use Room11\GoogleSearcher\SearchResultSet;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\Jeeves\Utf8Chars;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Client\MessageFetchFailureException;
use Room11\StackChat\Client\MessageResolver;

class Google extends BasePlugin
{
    private const MAX_RESULTS = 3;
    private const ENCODING = 'UTF-8';
    private const DEFAULT_PARAMS = [
        '-site:w3schools.com'
    ];

    private $chatClient;
    private $searcher;
    private $messageResolver;

    public function __construct(ChatClient $chatClient, GoogleSearcher $httpClient, MessageResolver $messageResolver)
    {
        $this->chatClient  = $chatClient;
        $this->searcher  = $httpClient;
        $this->messageResolver = $messageResolver;
    }

    private function postNoResultsMessage(Command $command): Promise
    {
        $message = sprintf(
            "Did you know? That `%s...` doesn't exist in the world! Cuz' GOOGLE can't find it :P",
            implode(' ', $command->getParameters())
        );

        return $this->chatClient->postReply($command, $message);
    }

    private function ellipsize(string $string, int $length): string
    {
        if (mb_strlen($string, self::ENCODING) < $length) {
            return $string;
        }

        return trim(mb_substr($string, 0, $length - 1, self::ENCODING)) . Utf8Chars::ELLIPSIS;
    }

    private function formatDescription(string $description): string
    {
        static $removeLineBreaksExpr = '#(?:\r?\n)+#';
        static $stripLeadingSeparatorExpr = '#^\s*(\.\.\.|-)\s*#u';

        $description = preg_replace($removeLineBreaksExpr, ' ', $description);
        $description = preg_replace($stripLeadingSeparatorExpr, '', $description);
        $description = str_replace('...', Utf8Chars::ELLIPSIS, $description);

        return $description;
    }

    /**
     * @param SearchResultSet $results
     * @return string
     */
    private function getFormattedResultsMessage(SearchResultSet $results): string
    {
        $message = sprintf('Search for "%s" (%s)', $results->getSearchTerm(), $results->getSearchUrl());

        foreach ($results->getResults() as $i => $result) {
            if ($i === self::MAX_RESULTS) {
                break;
            }

            $message .= sprintf(
                "\n%s %s - %s (%s)",
                Utf8Chars::BULLET,
                $this->ellipsize($result->getTitle(), 50),
                $this->ellipsize($this->formatDescription($result->getDescription()), 100),
                $result->getUrl()
            );
        }

        return $message;
    }

    public function search(Command $command)
    {
        if (!$command->hasParameters()) {
            return new Success();
        }
        
        $parameters = array_merge($command->getParameters(), self::DEFAULT_PARAMS);
        $text = implode(' ', $parameters);

        try {
            $searchTerm = yield $this->messageResolver->resolveMessageText($command->getRoom(), $text);
            $searchResults = yield $this->searcher->search($searchTerm);
        } catch (MessageFetchFailureException $e) {
            return $this->chatClient->postReply($command, 'Failed to get message text for search');
        } catch (SearchFailedException $e) {
            return $this->chatClient->postReply($command, "Error when searching Google: {$e->getMessage()}");
        }

        /** @var SearchResultSet $searchResults */
        if (count($searchResults->getResults()) === 0) {
            return $this->postNoResultsMessage($command);
        }

        $postMessage = $this->getFormattedResultsMessage($searchResults);

        return $this->chatClient->postMessage($command, $postMessage);
    }

    public function getDescription(): string
    {
        return 'Retrieves and displays search results from Google';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'google')];
    }
}
