<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\Cookie\Cookie;
use Amp\Artax\Cookie\CookieJar;
use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Room11\DOMUtils\ElementNotFoundException;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\PendingMessage;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Exception;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Amp\resolve;
use function Room11\DOMUtils\domdocument_load_html;
use function Room11\DOMUtils\xpath_get_element;
use function Room11\DOMUtils\xpath_get_elements;

class OpenGrokSearchFailureException extends Exception
{
    private $searchUrl;

    public function __construct(string $message, string $searchUrl)
    {
        parent::__construct($message);

        $this->searchUrl = $searchUrl;
    }

    public function getMessageText(): string
    {
        return sprintf($this->getMessage(), 'results page');
    }

    public function getMessageMarkdown(): string
    {
        return sprintf($this->getMessage(), "[results page]({$this->searchUrl})");
    }

    public function getSearchUrl(): string
    {
        return $this->searchUrl;
    }
}

interface Searcher
{
    function search(string $project, string $url): Promise;
    function getDefinitionSearchUrl(string $project, string $symbol): string;
    function getReferenceSearchUrl(string $project, string $symbol): string;
    function getFreeTextSearchUrl(string $project, string $text): string;
}

class OpenGrokSearchResult
{
    private $project;
    private $filePath;
    private $lineNo;
    private $code;

    public function __construct(string $project, string $filePath, int $lineNo, string $code)
    {
        $this->project = $project;
        $this->filePath = $filePath;
        $this->lineNo = $lineNo;
        $this->code = $code;
    }

    public function getProject(): string
    {
        return $this->project;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getLineNo(): int
    {
        return $this->lineNo;
    }

    public function getCode(): string
    {
        return $this->code;
    }
}

class OpenGrokSearchResultSet
{
    private $project;
    private $url;
    private $codeResults;
    private $testResults;

    public function __construct(string $project, string $url, array $codeResults, array $testResults)
    {
        $this->project = $project;
        $this->url = $url;
        $this->codeResults = $codeResults;
        $this->testResults = $testResults;
    }

    public function getProject(): string
    {
        return $this->project;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return OpenGrokSearchResult[]
     */
    public function getCodeResults(): array
    {
        return $this->codeResults;
    }

    /**
     * @return OpenGrokSearchResult[]
     */
    public function getTestResults(): array
    {
        return $this->testResults;
    }

    public function getResultCount()
    {
        return count($this->codeResults) + count($this->testResults);
    }
}

class OpenGrokHtmlSearcher implements Searcher
{
    private $httpClient;
    private $cookieJar;
    private $baseUrl;

    public function __construct(HttpClient $httpClient, CookieJar $cookieJar, string $baseUrl = OpenGrok::BASE_URL)
    {
        $this->baseUrl = $baseUrl;
        $this->httpClient = $httpClient;
        $this->cookieJar = $cookieJar;
    }

    private function getUrl(string $project, array $params): string
    {
        return $this->baseUrl . '/search?' . http_build_query($params + ['project' => $project, 'n' => 10000]);
    }

    private function getResults(string $project, string $url)
    {
        $this->cookieJar->store(new Cookie('OpenGrokProject', $project, null, null, $this->baseUrl));

        try {
            /** @var HttpResponse $response */
            /** @var \DOMDocument $doc */

            $response = yield $this->httpClient->request($url);
            $doc = domdocument_load_html($response->getBody());
        } catch (\Throwable $e) {
            throw new OpenGrokSearchFailureException('Totally failed to get a valid %s', $url);
        }

        if (!$resultsDiv = $doc->getElementById('results')) {
            throw new OpenGrokSearchFailureException('The %s is not in the expected format', $url);
        }

        try {
            $resultsTable = xpath_get_element($resultsDiv, './table');
        } catch (ElementNotFoundException $e) {
            return new OpenGrokSearchResultSet($project, $url, [], []);
        }

        $isCurrentDirTestSource = false;
        $trim = strlen('/xref/' . $project);
        $codeResults = $testResults = [];

        try {
            foreach ($resultsTable->getElementsByTagName('tr') as $row) {
                /** @var \DOMElement $row */
                if (preg_match('#\bdir\b#', $row->getAttribute('class'))) {
                    $isCurrentDirTestSource = (bool)preg_match('#/tests/#i', xpath_get_element($row, './td/a')->textContent);
                    continue;
                }

                foreach (xpath_get_elements($row, "./td/tt/a[@class='s']") as $resultAnchor) {
                    $hrefAttr = $resultAnchor->getAttribute('href');
                    list($path, $lineNo) = explode('#', substr($hrefAttr, $trim));

                    $el = xpath_get_element($resultAnchor, "./span[@class='l']");
                    $code = '';

                    while ($el = $el->nextSibling) {
                        $code .= $el->textContent;
                    }

                    $result = new OpenGrokSearchResult($project, $path, (int)$lineNo, trim(preg_replace('#\s+#', ' ', $code)));

                    if ($isCurrentDirTestSource) {
                        $testResults[] = $result;
                    } else {
                        $codeResults[] = $result;
                    }
                }
            }
        } catch (ElementNotFoundException $e) { /* ignore this and keep trying to make a result set */ }

        return new OpenGrokSearchResultSet($project, $url, $codeResults, $testResults);
    }

    public function search(string $project, string $url): Promise
    {
        return resolve($this->getResults($project, $url));
    }

    public function getDefinitionSearchUrl(string $project, string $symbol): string
    {
        return $this->getUrl($project, ['defs' => $symbol]);
    }

    public function getReferenceSearchUrl(string $project, string $symbol): string
    {
        return $this->getUrl($project, ['defs' => $symbol]);
    }

    public function getFreeTextSearchUrl(string $project, string $text): string
    {
        return $this->getUrl($project, ['q' => $text]);
    }
}

interface OpenGrokSearchResultProcessor
{
    /**
     * @param OpenGrokSearchResultSet $results
     * @param string $searchTerm
     * @return OpenGrokSearchResult|null
     */
    function processDefinitionSearchResults(OpenGrokSearchResultSet $results, string $searchTerm);

    /**
     * @param OpenGrokSearchResultSet $results
     * @param string $searchTerm
     * @return OpenGrokSearchResult|null
     */
    function processReferenceSearchResults(OpenGrokSearchResultSet $results, string $searchTerm);

    /**
     * @param OpenGrokSearchResultSet $results
     * @param string $searchTerm
     * @return OpenGrokSearchResult|null
     */
    function processFreeTextSearchResults(OpenGrokSearchResultSet $results, string $searchTerm);
}

class PhpSrcOpenGrokSearchResultProcessor implements OpenGrokSearchResultProcessor
{
    private function findCSymbolDefinition(OpenGrokSearchResultSet $results, string $searchTerm)
    {
        $searchTerm = preg_quote($searchTerm, '/');

        $exprs = [
            '/^#\s*def(ine)?\s+' . $searchTerm . '\b/i',                          // macro #define
            '/^(?:[a-z_][a-z0-9_]*\s+)+\**\s*' . $searchTerm . '\s*\([^;]+$/i',   // function definition
            '/^struct\s+' . $searchTerm . '/i',                                   // struct definition
            '/^typedef\s+(?:struct\s+)?[a-z_][a-z0-9_]*\s+' . $searchTerm . '/i', // typedef
        ];

        foreach ($exprs as $expr) {
            foreach ($results->getCodeResults() as $result) {
                if (preg_match($expr, $result->getCode())) {
                    return $result;
                }
            }
        }

        return null;
    }

    private function findPHPSymbolDefinition(OpenGrokSearchResultSet $results, string $searchTerm)
    {
        $searchTerm = preg_quote($searchTerm, '/');

        $exprs = [
            '/^(?:[a-z_][a-z0-9_]*\s+)*(?:PHP|ZEND)_FUNCTION\s*\(\s*' . $searchTerm . '(?!.*;)/i', // PHP_FUNCTION def
            '/^(?:[a-z_][a-z0-9_]*\s+)*(?:PHP|ZEND)_NAMED_FUNCTION\s*\(.*?if_' . $searchTerm . '\)(?!.*;)/i', // PHP_NAMED_FUNCTION def
        ];

        foreach ($exprs as $k => $expr) {
            foreach ($results->getCodeResults() as $result) {
                if (preg_match($expr, $result->getCode())) {
                    return $result;
                }
            }
        }

        return null;
    }

    private function findPrototypeComment(OpenGrokSearchResultSet $results, string $searchTerm)
    {
        $pattern = '~/\*\s*{{{\s*proto\s*\w+\s*' . preg_quote($searchTerm) . '\s*\(~';

        foreach ($results->getCodeResults() as $result) {
            if (preg_match($pattern, $result->getCode())) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function processDefinitionSearchResults(OpenGrokSearchResultSet $results, string $searchTerm)
    {
        return $this->findCSymbolDefinition($results, $searchTerm);
    }

    /**
     * @inheritdoc
     */
    public function processReferenceSearchResults(OpenGrokSearchResultSet $results, string $searchTerm)
    {
        if (null !== $result = $this->findCSymbolDefinition($results, $searchTerm)) {
            return $result;
        }

        if (null !== $result = $this->findPHPSymbolDefinition($results, $searchTerm)) {
            return $result;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function processFreeTextSearchResults(OpenGrokSearchResultSet $results, string $searchTerm)
    {
        if (null !== $result = $this->processReferenceSearchResults($results, $searchTerm)) {
            return $result;
        }

        return $this->findPrototypeComment($results, $searchTerm);
    }
}

class OpenGrok extends BasePlugin
{
    const BASE_URL = 'https://lxr.room11.org';
    const RESULT_PROCESSORS = [ // keys are lower-case
        'php-src' => PhpSrcOpenGrokSearchResultProcessor::class,
    ];

    private $chatClient;
    private $httpClient;
    private $cookieJar;
    private $htmlSearcher;

    public function __construct(
        ChatClient $chatClient,
        HttpClient $httpClient,
        CookieJar $cookieJar,
        OpenGrokHtmlSearcher $htmlSearcher
    ) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->cookieJar  = $cookieJar;
        $this->htmlSearcher = $htmlSearcher;
    }

    private function parseArguments(Command $command): array
    {
        $parameters = $command->getParameters();

        for ($i = 0; isset($parameters[$i]); $i++) {
            if ($parameters[$i] === '--project') {
                $project = $parameters[$i + 1];
            } else if (substr($parameters[$i], 0, 10) === '--project=') {
                $project = substr($parameters[$i], 10);
            } else if ($parameters[$i] === '--processor') {
                $processor = $parameters[$i + 1];
            } else if (substr($parameters[$i], 0, 12) === '--processor=') {
                $processor = substr($parameters[$i], 12);
            } else {
                $search = implode(' ', array_slice($parameters, $i));
            }
        }

        if (!isset($project)) {
            throw new \InvalidArgumentException('Project not specified, the --project argument is required');
        }

        if (!isset($processor)) {
            throw new \InvalidArgumentException('Processor not specified, the --processor argument is required');
        }

        if (!array_key_exists(strtolower($processor), self::RESULT_PROCESSORS)) {
            throw new \InvalidArgumentException('Unknown result processor ' . $processor);
        }

        $processorClass = self::RESULT_PROCESSORS[$processor];

        if (!isset($search)) {
            throw new \InvalidArgumentException('Search term not specified');
        }

        return [$project, new $processorClass, $search];
    }

    private function formatResultMessage(OpenGrokSearchResult $result): string
    {
        return sprintf(
            '[ [%s](%s) ] `%s`',
            $result->getFilePath() . '#' . $result->getLineNo(),
            self::BASE_URL . '/xref/' . $result->getProject() . $result->getFilePath() . '#' . $result->getLineNo(),
            $result->getCode()
        );
    }

    public function grok(Command $command)
    {
        if (!$command->hasParameters()) {
            return null;
        }

        try {
            list($project, $processor, $searchTerm) = $this->parseArguments($command);
        } catch (\InvalidArgumentException $e) {
            return $this->chatClient->postReply($command, new PendingMessage($e->getMessage(), $command));
        }

        /** @var OpenGrokSearchResultSet $results */
        /** @var OpenGrokSearchResultProcessor $processor */

        try {
            $url = $this->htmlSearcher->getDefinitionSearchUrl($project, $searchTerm);
            $results = yield $this->htmlSearcher->search($project, $url);
        } catch (OpenGrokSearchFailureException $e) {
            return $this->chatClient->postReply($command, new PendingMessage($e->getMessageMarkdown(), $command));
        }

        if (null !== $result = $processor->processDefinitionSearchResults($results, $searchTerm)) {
            return $this->chatClient->postMessage(
                $command->getRoom(),
                new PendingMessage($this->formatResultMessage($result), $command)
            );
        }

        try {
            $url = $this->htmlSearcher->getReferenceSearchUrl($project, $searchTerm);
            $results = yield $this->htmlSearcher->search($project, $url);
        } catch (OpenGrokSearchFailureException $e) {
            return $this->chatClient->postReply($command, new PendingMessage($e->getMessageMarkdown(), $command));
        }

        if (null !== $result = $processor->processReferenceSearchResults($results, $searchTerm)) {
            return $this->chatClient->postMessage(
                $command->getRoom(),
                new PendingMessage($this->formatResultMessage($result), $command)
            );
        }

        try {
            $url = $this->htmlSearcher->getFreeTextSearchUrl($project, $searchTerm);
            $results = yield $this->htmlSearcher->search($project, $url);
        } catch (OpenGrokSearchFailureException $e) {
            return $this->chatClient->postReply($command, new PendingMessage($e->getMessageMarkdown(), $command));
        }

        if (null !== $result = $processor->processFreeTextSearchResults($results, $searchTerm)) {
            return $this->chatClient->postMessage(
                $command->getRoom(),
                new PendingMessage($this->formatResultMessage($result), $command)
            );
        }

        return $this->chatClient->postReply($command, 'Nothing went wrong but I couldn\'t find a suitable definition');
    }

    public function getDescription(): string
    {
        return 'Retrieves and displays search results from lxr.php.net';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint('Grok', [$this, 'grok'], 'grok', 'Retrieves and displays definition search results from OpenGrok'),
        ];
    }
}
