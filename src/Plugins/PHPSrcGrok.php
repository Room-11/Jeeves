<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\Cookie\Cookie;
use Amp\Artax\Cookie\CookieJar;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Artax\Uri;
use Room11\DOMUtils\ElementNotFoundException;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Room11\DOMUtils\domdocument_load_html;
use function Room11\DOMUtils\xpath_get_element;
use function Room11\DOMUtils\xpath_get_elements;

class OpenGrokSearchFailureException extends \RuntimeException {}

class PHPSrcGrok extends BasePlugin
{
    const DEFAULT_BRANCH = 'PHP-MASTER';
    const BASE_URL = 'https://opengrok02.lxr.room11.org/source/search';

    private $chatClient;
    private $httpClient;
    private $cookieJar;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, CookieJar $cookieJar) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->cookieJar  = $cookieJar;
    }

    private function getBranchAndSearchTerm(Command $command): array
    {
        $parameters = $command->getParameters();

        if (count($parameters) < 3
            || strtolower($command->getParameter(0)) !== '-b'
            || !preg_match('#^(?:[0-9]+\.[0-9]+|PECL|MASTER)$#i', $command->getParameter(1))) {
            return [self::DEFAULT_BRANCH, implode(' ', $parameters)];
        }

        return [strtoupper($command->getParameter(1)), implode(' ', array_slice($parameters, 2))];
    }

    private function getOpenGrokSearchResults(string $branch, array $params)
    {
        $branch = ($branch === self::DEFAULT_BRANCH) ? $branch : $branch = 'PHP-' . $branch;
        $url = self::BASE_URL . '?project=' . $branch . '&n=10000&' . http_build_query($params);

        try {
            $request = (new HttpRequest)
                ->setMethod('GET')
                ->setUri($url);

            $this->cookieJar->store(new Cookie('OpenGrokProject', $branch, null, null, self::BASE_URL));

            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($request);

            /** @var \DOMDocument $doc */
            $doc = domdocument_load_html($response->getBody());
        } catch(\Throwable $e) {
            throw new OpenGrokSearchFailureException("Totally failed to get a valid [results page]({$url})", 1);
        }

        if (!$resultsDiv = $doc->getElementById('results')) {
            throw new OpenGrokSearchFailureException("The [results page]({$url}) is not in the format I expected it to be", 1);
        }

        try {
            $resultsTable = xpath_get_element($resultsDiv, './table');
        } catch (ElementNotFoundException $e) {
            throw new OpenGrokSearchFailureException("There were no [results]({$url}) for that search", 0);
        }

        $dir = null;
        $tests = false;
        $trim = strlen('/xref/' . $branch);

        $results = [
            'url' => $url,
            'count' => 0,
            'code' => [],
            'tests' => [],
        ];

        $baseUrl = new Uri($url);

        foreach ($resultsTable->getElementsByTagName('tr') as $row) {
            /** @var \DOMElement $row */
            if (preg_match('#\bdir\b#', $row->getAttribute('class'))) {
                $tests = (bool)preg_match('#/tests/#i', xpath_get_element($row, './td/a')->textContent);
                continue;
            }

            foreach (xpath_get_elements($row, "./td/tt/a[@class='s']") as $resultAnchor) {
                $hrefAttr = $resultAnchor->getAttribute('href');
                $path = substr($hrefAttr, $trim);
                $href = (string)$baseUrl->resolve($hrefAttr);
                $el = xpath_get_element($resultAnchor, "./span[@class='l']");
                $line = $el->textContent;
                $code = '';

                while ($el = $el->nextSibling) {
                    $code .= $el->textContent;
                }

                $results[$tests ? 'tests' : 'code'][] = [
                    'href' => $href,
                    'path' => $path,
                    'line' => $line,
                    'code' => trim(preg_replace('#\s+#', ' ', $code)),
                ];
                $results['count']++;
            }
        }

        return $results;
    }

    /**
     * @param array $results
     * @param string $searchTerm
     * @return array|null
     */
    private function findCSymbolDefinition(array $results, string $searchTerm)
    {
        $searchTerm = preg_quote($searchTerm, '/');

        $exprs = [
            '/^#\s*def(ine)?\s+' . $searchTerm . '\b/i',                          // macro #define
            '/^(?:[a-z_][a-z0-9_]*\s+)+\**\s*' . $searchTerm . '\s*\([^;]+$/i',   // function definition
            '/^struct\s+' . $searchTerm . '/i',                                   // struct definition
            '/^typedef\s+(?:struct\s+)?[a-z_][a-z0-9_]*\s+' . $searchTerm . '/i', // typedef
        ];

        foreach ($exprs as $expr) {
            foreach ($results['code'] ?? [] as $result) {
                if (preg_match($expr, $result['code'])) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * @param array $results
     * @param string $searchTerm
     * @return array|null
     */
    private function findPHPSymbolDefinition(array $results, string $searchTerm)
    {
        $searchTerm = preg_quote($searchTerm, '/');

        $exprs = [
            '/^(?:[a-z_][a-z0-9_]*\s+)*(?:PHP|ZEND)_FUNCTION\s*\(\s*' . $searchTerm . '(?!.*;)/i', // PHP_FUNCTION def
            '/^(?:[a-z_][a-z0-9_]*\s+)*(?:PHP|ZEND)_NAMED_FUNCTION\s*\(.*?if_' . $searchTerm . '\)(?!.*;)/i', // PHP_NAMED_FUNCTION def
        ];

        foreach ($exprs as $k => $expr) {
            foreach ($results['code'] ?? [] as $result) {
                if (preg_match($expr, $result['code'])) {
                    return $result;
                }
            }
        }

        return null;
    }

    private function formatResultMessage(array $result)
    {
        return sprintf('[ [%s](%s) ] `%s`', $result['path'], $result['href'], trim($result['code']));
    }

    public function getDefinition(Command $command): \Generator
    {
        if (!$command->hasParameters()) {
            return;
        }

        list($branch, $searchTerm) = $this->getBranchAndSearchTerm($command);

        try {
            $results = yield from $this->getOpenGrokSearchResults($branch, ['defs' => $searchTerm]);
        } catch (OpenGrokSearchFailureException $e) {
            if ($e->getCode()) {
                yield from $this->chatClient->postReply($command, $e->getMessage());
                return;
            }

            $results = [];
        }

        if ($result = $this->findCSymbolDefinition($results, $searchTerm)) {
            yield from $this->chatClient->postMessage($command->getRoom(), $this->formatResultMessage($result));
            return;
        }

        try {
            $results = yield from $this->getOpenGrokSearchResults($branch, ['refs' => $searchTerm]);
        } catch (OpenGrokSearchFailureException $e) {
            yield from $this->chatClient->postReply($command, $e->getMessage());
            return;
        }

        if ($result = $this->findPHPSymbolDefinition($results, $searchTerm)) {
            yield from $this->chatClient->postMessage($command->getRoom(), $this->formatResultMessage($result));
            return;
        }

        // fall back to full search if no results have been found
        yield from $this->getFullSearch($command);
    }

    /*
    public function getReference(Command $command): \Generator
    {

    }
    */

    public function getFullSearch(Command $command): \Generator
    {
        if (!$command->hasParameters()) {
            return;
        }

        list($branch, $searchTerm) = $this->getBranchAndSearchTerm($command);

        try {
            $results = yield from $this->getOpenGrokSearchResults($branch, ['q' => $searchTerm]);
        } catch (OpenGrokSearchFailureException $e) {
            if ($e->getCode()) {
                yield from $this->chatClient->postReply($command, $e->getMessage());
                return;
            }

            $results = [];
        }

        $pattern = '~^/\* {{{ proto \w+ ' . preg_quote($searchTerm) . '\(~';

        if (isset($results['code'])) {
            foreach ($results['code'] as $result) {
                if (preg_match($pattern, $result['code'])) {
                    yield from $this->chatClient->postMessage($command->getRoom(), $this->formatResultMessage($result));
                    return;
                }
            }
        }

        yield from $this->chatClient->postReply(
            $command, 'Nothing went wrong but I couldn\'t find a suitable definition. Ping DaveRandom or Peehaa if you think I should have done.'
        );
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
            new PluginCommandEndpoint('Def', [$this, 'getDefinition'], 'lxr', 'Retrieves and displays definition search results from lxr.php.net'),
//            new PluginCommandEndpoint('Ref', [$this, 'getReference'], 'refs', 'Retrieves and displays symbol search results from lxr.php.net'),
//            new PluginCommandEndpoint('Full', [$this, 'getFullSearch'], 'lxr', 'Retrieves and displays definition search results from lxr.php.net'),
        ];
    }
}
