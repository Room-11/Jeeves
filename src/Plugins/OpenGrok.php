<?php declare(strict_types = 1);

namespace Room11\Jeeves\OpenGrokClient
{

    use Amp\Artax\Cookie\Cookie;
    use Amp\Artax\Cookie\CookieJar;
    use Amp\Artax\HttpClient;
    use Amp\Artax\Response as HttpResponse;
    use Amp\Promise;
    use Room11\DOMUtils\ElementNotFoundException;
    use Room11\Jeeves\Exception;
    use Room11\Jeeves\Plugins\OpenGrok;
    use function Amp\resolve;
    use function Room11\DOMUtils\domdocument_load_html;
    use function Room11\DOMUtils\xpath_get_element;
    use function Room11\DOMUtils\xpath_get_elements;

    interface Searcher
    {
        function searchDefinitions(string $project, string $symbol): Promise;
        function searchReferences(string $project, string $symbol): Promise;
        function searchFreeText(string $project, string $text): Promise;
    }

    interface SearchResultProcessor
    {
        /**
         * @param SearchResultSet $results
         * @param string $searchTerm
         * @return SearchResult|null
         */
        function processDefinitionSearchResults(SearchResultSet $results, string $searchTerm);

        /**
         * @param SearchResultSet $results
         * @param string $searchTerm
         * @return SearchResult|null
         */
        function processReferenceSearchResults(SearchResultSet $results, string $searchTerm);

        /**
         * @param SearchResultSet $results
         * @param string $searchTerm
         * @return SearchResult|null
         */
        function processFreeTextSearchResults(SearchResultSet $results, string $searchTerm);
    }

    class SearchFailureException extends Exception
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

    class SearchResult
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
            return urldecode($this->filePath);
        }

        public function getFileHref(): string
        {
            return '/xref/' . urlencode($this->project) . $this->filePath . '#' . $this->lineNo;
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

    class SearchResultSet
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
         * @return SearchResult[]
         */
        public function getCodeResults(): array
        {
            return $this->codeResults;
        }

        /**
         * @return SearchResult[]
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

    class HtmlSearcher implements Searcher
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

        private function makeSearchUrl(string $project, array $params): string
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
                throw new SearchFailureException('Totally failed to get a valid %s', $url);
            }

            if (!$resultsDiv = $doc->getElementById('results')) {
                throw new SearchFailureException('The %s is not in the expected format', $url);
            }

            try {
                $resultsTable = xpath_get_element($resultsDiv, './table');
            } catch (ElementNotFoundException $e) {
                return new SearchResultSet($project, $url, [], []);
            }

            $isCurrentDirTestSource = false;
            $trimLength = strlen('/xref/' . urlencode($project));
            $codeResults = $testResults = [];

            foreach ($resultsTable->getElementsByTagName('tr') as $row) {
                try {
                    /** @var \DOMElement $row */
                    if (preg_match('#\bdir\b#', $row->getAttribute('class'))) {
                        $isCurrentDirTestSource = (bool)preg_match('#/tests/#i', xpath_get_element($row, './td/a')->textContent);
                        continue;
                    }

                    foreach (xpath_get_elements($row, "./td/tt/a[@class='s']") as $resultAnchor) {
                        $hrefAttr = $resultAnchor->getAttribute('href');
                        list($path, $lineNo) = explode('#', substr($hrefAttr, $trimLength));

                        $el = xpath_get_element($resultAnchor, "./span[@class='l']");
                        $code = '';

                        while ($el = $el->nextSibling) {
                            if ($el instanceof \DOMElement && $el->tagName === 'i') {
                                break;
                            }

                            $code .= $el->textContent;
                        }

                        $result = new SearchResult($project, $path, (int)$lineNo, trim(preg_replace('#\s+#', ' ', $code)));

                        if ($isCurrentDirTestSource) {
                            $testResults[] = $result;
                        } else {
                            $codeResults[] = $result;
                        }
                    }
                } catch (ElementNotFoundException $e) { /* ignore this and keep trying to make a result set */ }
            }

            return new SearchResultSet($project, $url, $codeResults, $testResults);
        }

        public function searchDefinitions(string $project, string $symbol): Promise
        {
            return resolve($this->getResults($project, $this->makeSearchUrl($project, ['defs' => $symbol])));
        }

        public function searchReferences(string $project, string $symbol): Promise
        {
            return resolve($this->getResults($project, $this->makeSearchUrl($project, ['refs' => $symbol])));
        }

        public function searchFreeText(string $project, string $text): Promise
        {
            return resolve($this->getResults($project, $this->makeSearchUrl($project, ['q' => $text])));
        }
    }

    class PhpSrcResultProcessor implements SearchResultProcessor
    {
        private function findCSymbolDefinition(SearchResultSet $results, string $searchTerm)
        {
            $searchTerm = preg_quote($searchTerm, '/');

            $exprs = [
                '/^#\s*def(ine)?\s+' . $searchTerm . '\b/i',                          // macro #define
                '/^(?:[a-z_][a-z0-9_]*\s+)+\**\s*' . $searchTerm . '\s*\([^;]+$/i',   // function definition
                '/^struct\s+' . $searchTerm . '/i',                                   // struct definition
                '/^typedef\s+(?:struct\s+)?[a-z_][a-z0-9_]*\s+' . $searchTerm . '/i', // typedef
                '/^}\s*' . $searchTerm . '\s*;/',                                     // typedef struct type name
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

        private function findPHPSymbolDefinition(SearchResultSet $results, string $searchTerm)
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

        private function findPrototypeComment(SearchResultSet $results, string $searchTerm)
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
        public function processDefinitionSearchResults(SearchResultSet $results, string $searchTerm)
        {
            return $this->findCSymbolDefinition($results, $searchTerm);
        }

        /**
         * @inheritdoc
         */
        public function processReferenceSearchResults(SearchResultSet $results, string $searchTerm)
        {
            if (null !== $result = $this->findCSymbolDefinition($results, $searchTerm)) {
                return $result;
            }

            return $this->findPHPSymbolDefinition($results, $searchTerm);
        }

        /**
         * @inheritdoc
         */
        public function processFreeTextSearchResults(SearchResultSet $results, string $searchTerm)
        {
            if (null !== $result = $this->findCSymbolDefinition($results, $searchTerm)) {
                return $result;
            }

            if (null !== $result = $this->findPHPSymbolDefinition($results, $searchTerm)) {
                return $result;
            }

            return $this->findPrototypeComment($results, $searchTerm);
        }
    }

    class GenericPhpResultProcessor implements SearchResultProcessor
    {
        private function findPhpSymbolDefinition(SearchResultSet $results, string $searchTerm)
        {
            $searchTerm = preg_quote($searchTerm, '/');

            $exprs = [
                '/class\s+' . $searchTerm . '\b/i',
                '/interface\s+' . $searchTerm . '\b/i',
                '/trait\s+' . $searchTerm . '\b/i',
                '/function\s+' . $searchTerm . '\b/i',
                '/const\s+' . $searchTerm . '\b/i',
                '/define\s*\(\s*[\'"]' . $searchTerm . '[\'"]/i',
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

        /**
         * @inheritdoc
         */
        public function processDefinitionSearchResults(SearchResultSet $results, string $searchTerm)
        {
            return $this->findPhpSymbolDefinition($results, $searchTerm);
        }

        /**
         * @inheritdoc
         */
        public function processReferenceSearchResults(SearchResultSet $results, string $searchTerm)
        {
            return $this->findPhpSymbolDefinition($results, $searchTerm);
        }

        /**
         * @inheritdoc
         */
        public function processFreeTextSearchResults(SearchResultSet $results, string $searchTerm)
        {
            return $this->findPhpSymbolDefinition($results, $searchTerm);
        }
    }
}

namespace Room11\Jeeves\Plugins
{

    use Amp\Artax\Cookie\CookieJar;
    use Amp\Artax\HttpClient;
    use Room11\Jeeves\Chat\Command;
    use Room11\Jeeves\OpenGrokClient\GenericPhpResultProcessor;
    use Room11\Jeeves\OpenGrokClient\HtmlSearcher;
    use Room11\Jeeves\OpenGrokClient\PhpSrcResultProcessor;
    use Room11\Jeeves\OpenGrokClient\Searcher;
    use Room11\Jeeves\OpenGrokClient\SearchFailureException;
    use Room11\Jeeves\OpenGrokClient\SearchResult;
    use Room11\Jeeves\OpenGrokClient\SearchResultProcessor;
    use Room11\Jeeves\OpenGrokClient\SearchResultSet;
    use Room11\Jeeves\System\PluginCommandEndpoint;
    use Room11\StackChat\Client\Client as ChatClient;

    class OpenGrok extends BasePlugin
    {
        const BASE_URL = 'https://lxr.room11.org';

        private const SEARCHER_CLASS = HtmlSearcher::class;
        private const RESULT_PROCESSORS = [ // keys are lower-case
            'php-src' => PhpSrcResultProcessor::class,
            'php'     => GenericPhpResultProcessor::class,
        ];

        private $chatClient;
        private $httpClient;
        private $cookieJar;

        /**
         * @var Searcher
         */
        private $searcher;

        public function __construct(
            ChatClient $chatClient,
            HttpClient $httpClient,
            CookieJar $cookieJar
        ) {
            $this->chatClient = $chatClient;
            $this->httpClient = $httpClient;
            $this->cookieJar  = $cookieJar;

            $searcherClass = self::SEARCHER_CLASS;
            $this->searcher = new $searcherClass($httpClient, $cookieJar, self::BASE_URL);
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

        private function formatResultMessage(SearchResult $result): string
        {
            return sprintf(
                '[ [%s](%s) ] `%s`',
                $result->getFilePath() . '#' . $result->getLineNo(),
                self::BASE_URL . $result->getFileHref(),
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
                return $this->chatClient->postReply($command, $e->getMessage());
            }

            /** @var SearchResultSet $results */
            /** @var SearchResultProcessor $processor */

            try {
                $results = yield $this->searcher->searchDefinitions($project, $searchTerm);
            } catch (SearchFailureException $e) {
                return $this->chatClient->postReply($command, $e->getMessageMarkdown());
            }

            if (null !== $result = $processor->processDefinitionSearchResults($results, $searchTerm)) {
                return $this->chatClient->postMessage($command, $this->formatResultMessage($result));
            }

            try {
                $results = yield $this->searcher->searchReferences($project, $searchTerm);
            } catch (SearchFailureException $e) {
                return $this->chatClient->postReply($command, $e->getMessageMarkdown());
            }

            if (null !== $result = $processor->processReferenceSearchResults($results, $searchTerm)) {
                return $this->chatClient->postMessage($command, $this->formatResultMessage($result));
            }

            try {
                $results = yield $this->searcher->searchFreeText($project, $searchTerm);
            } catch (SearchFailureException $e) {
                return $this->chatClient->postReply($command, $e->getMessageMarkdown());
            }

            if (null !== $result = $processor->processFreeTextSearchResults($results, $searchTerm)) {
                return $this->chatClient->postMessage($command, $this->formatResultMessage($result));
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
}
