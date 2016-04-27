<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;
use Amp\Artax\Response;

class NoComprendeException extends \RuntimeException {}

class Docs implements Plugin
{
    const COMMAND = 'docs';

    const URL_BASE = 'http://php.net';
    const LOOKUP_URL_BASE = self::URL_BASE . '/manual-lookup.php?scope=quickref&pattern=';
    const MANUAL_URL_BASE = self::URL_BASE . '/manual/en';

    private $chatClient;

    private $specialCases = [
        '::' => '[`::`](' . self::MANUAL_URL_BASE . '/language.oop5.paamayim-nekudotayim.php) is the scope resolution operator',
        '$_cookie' => '[`$_COOKIE`](' . self::MANUAL_URL_BASE . '/reserved.variables.cookie.php)',
        '$_env' => '[`$_ENV`](' . self::MANUAL_URL_BASE . '/reserved.variables.env.php)',
        '$_files' => '[`$_FILES`](' . self::MANUAL_URL_BASE . '/reserved.variables.files.php)',
        '$_get' => '[`$_GET`](' . self::MANUAL_URL_BASE . '/reserved.variables.get.php)',
        '$_post' => '[`$_POST`](' . self::MANUAL_URL_BASE . '/reserved.variables.post.php)',
        '$_request' => '[`$_REQUEST`](' . self::MANUAL_URL_BASE . '/reserved.variables.request.php)',
        '$_server' => '[`$_SERVER`](' . self::MANUAL_URL_BASE . '/reserved.variables.server.php)',
        '$_session' => '[`$_SESSION`](' . self::MANUAL_URL_BASE . '/reserved.variables.session.php)',
        'abstract' => '[Class abstraction](' . self::MANUAL_URL_BASE . '/language.oop5.abstract.php)',
        'arrays' => '[Arrays](' . self::MANUAL_URL_BASE . '/language.types.array.php)',
        'autoloading' => '[Autoloading Classes](' . self::MANUAL_URL_BASE . '/language.oop5.autoload.php)',
        'bools' => '[Booleans](' . self::MANUAL_URL_BASE . '/language.types.boolean.php)',
        'booleans' => '[Booleans](' . self::MANUAL_URL_BASE . '/language.types.boolean.php)',
        'callables' => '[Callbacks / Callables](' . self::MANUAL_URL_BASE . '/language.types.callable.php)',
        'casts' => '[Type juggling](' . self::MANUAL_URL_BASE . '/language.types.type-juggling.php)',
        'class' => '[Classes and objects](' . self::MANUAL_URL_BASE . '/language.oop5.php) (Object-oriented programming)',
        'clone' => '[Object cloning](' . self::MANUAL_URL_BASE . '/language.oop5.cloning.php)',
        'context' => '[Stream context options and parameters](' . self::MANUAL_URL_BASE . '/context.php',
        'const' => '`const` can be used to define [constants](' . self::MANUAL_URL_BASE . '/language.constants.syntax.php)'
                 . ' and [class constants](' . self::MANUAL_URL_BASE . '/language.oop5.constants.php)',
        'errors' => '[Error handling](' . self::MANUAL_URL_BASE . '/language.errors.php)',
        'exceptions' => '[Exceptions](' . self::MANUAL_URL_BASE . '/language.exceptions.php)',
        'extends' => '[OOP inheritance](' . self::MANUAL_URL_BASE . '/language.oop5.inheritance.php)',
        'final' => '[`final` keyword](' . self::MANUAL_URL_BASE . '/language.oop5.final.php)',
        'floats' => '[Floating point numbers](' . self::MANUAL_URL_BASE . '/language.types.float.php)',
        'functions' => '[Functions](' . self::MANUAL_URL_BASE . '/language.functions.php)',
        'generators' => '[Generators](' . self::MANUAL_URL_BASE . '/language.generators.php)',
        'global' => 'Global ---All--- None Of The Things!',
        'hints' => '[Type hinting](' . self::MANUAL_URL_BASE . '/language.oop5.typehinting.php)',
        'ints' => '[Integers](' . self::MANUAL_URL_BASE . '/language.types.integer.php)',
        'implements' => '[Interfaces](' . self::MANUAL_URL_BASE . '/language.oop5.interfaces.php)',
        'integers' => '[Integers](' . self::MANUAL_URL_BASE . '/language.types.integer.php)',
        'interfaces' => '[Interfaces](' . self::MANUAL_URL_BASE . '/language.oop5.interfaces.php)',
        'javascript' => 'I think you\'re in the [wrong room](http://chat.stackoverflow.com/rooms/17/javascript).',
        'magic' => 'PHP was designed by wizards and so uses magic extensively.'
                 . ' [Magic constants](' . self::MANUAL_URL_BASE . '/language.constants.predefined.php) and'
                 . ' [magic methods](' . self::MANUAL_URL_BASE . '/language.oop5.magic.php) are both available.',
        'namespaces' => '[Namespaces](' . self::MANUAL_URL_BASE . '/language.namespaces.php)',
        'null' => '[NULL](' . self::MANUAL_URL_BASE . '/language.types.null.php)',
        'objects' => '[Objects](' . self::MANUAL_URL_BASE . '/language.types.object.php)',
        'oop' => '[Classes and objects](' . self::MANUAL_URL_BASE . '/language.oop5.php) (Object-oriented programming)',
        'operators' => '[Operators](' . self::MANUAL_URL_BASE . '/language.operators.php)',
        'refs' => '[References Explained](' . self::MANUAL_URL_BASE . '/language.references.php)',
        'references' => '[References Explained](' . self::MANUAL_URL_BASE . '/language.references.php)',
        'resources' => '[Resources](' . self::MANUAL_URL_BASE . '/language.types.resource.php)',
        'return' => '[Returning values](' . self::MANUAL_URL_BASE . '/functions.returning-values.php)',
        'precedence' => '[Operator precedence](' . self::MANUAL_URL_BASE . '/language.operators.precedence.php)',
        'private' => '[Class member visibility](' . self::MANUAL_URL_BASE . '/language.oop5.visibility.php)',
        'properties' => '[Object Properties](' . self::MANUAL_URL_BASE . '/language.oop5.properties.php)',
        'propertys' => '[Object Properties](' . self::MANUAL_URL_BASE . '/language.oop5.properties.php)',
        'protected' => '[Class member visibility](' . self::MANUAL_URL_BASE . '/language.oop5.visibility.php)',
        'public' => '[Class member visibility](' . self::MANUAL_URL_BASE . '/language.oop5.visibility.php)',
        'scopes' => '[Variable scope](' . self::MANUAL_URL_BASE . '/language.variables.scope.php)',
        'static' => 'The `static` keyword can be used to create [static class members](' . self::MANUAL_URL_BASE . '/language.oop5.static.php)'
                  . ' and [static variables](' . self::MANUAL_URL_BASE . '/language.variables.scope.php#language.variables.scope.static).'
                  . ' In general, you shouldn\'t be doing either of these!',
        'strings' => '[Strings](' . self::MANUAL_URL_BASE . '/language.types.string.php)',
        'types' => '[Type juggling](' . self::MANUAL_URL_BASE . '/language.types.type-juggling.php)',
        'traits' => '[Traits](' . self::MANUAL_URL_BASE . '/language.oop5.traits.php)',
        'tags' => '[PHP tags](' . self::MANUAL_URL_BASE . '/language.basic-syntax.phptags.php)',
        'vars' => '[Variables](' . self::MANUAL_URL_BASE . '/language.variables.php)',
        'use' => 'The `use` keyword is used to [import symbols from another namespace](' . self::MANUAL_URL_BASE . '/language.namespaces.importing.php)'
               . ' and to import a [trait](' . self::MANUAL_URL_BASE . '/language.oop5.traits.php) into a class.',
        'variables' => '[Variables](' . self::MANUAL_URL_BASE . '/language.variables.php)',
        'yeild' => '[Generators](' . self::MANUAL_URL_BASE . '/language.generators.php)',
        'yield' => '[Generators](' . self::MANUAL_URL_BASE . '/language.generators.php)',
    ];

    public function __construct(ChatClient $chatClient) {
        $this->chatClient = $chatClient;
    }

    public function handle(Message $message): \Generator {
        if (!$this->validMessage($message)) {
            return;
        }

        /** @var Command $message */
        yield from $this->getResult($message);
    }

    private function validMessage(Message $message): bool {
        return $message instanceof Command
            && $message->getCommand() === self::COMMAND
            && $message->getParameters();
    }

    private function getResult(Command $message): \Generator {
        $pattern = strtolower(implode(' ', $message->getParameters()));

        foreach ([$pattern, '$' . $pattern, $pattern . 's', $pattern . 'ing'] as $candidate) {
            if (isset($this->specialCases[$candidate])) {
                yield from $this->chatClient->postMessage(
                    $this->specialCases[$candidate]
                );

                return;
            }
        }

        if (substr($pattern, 0, 6) === "mysql_") {
            yield from $this->chatClient->postMessage(
                $this->getMysqlMessage()
            );

            return;
        }

        $pattern = str_replace('::', '.', $pattern);
        $url = self::LOOKUP_URL_BASE . rawurlencode($pattern);

        /** @var Response $response */
        $response = yield from $this->chatClient->request($url);

        if ($response->getPreviousResponse() !== null) {
            yield from $this->chatClient->postMessage(
                $this->getMessageFromMatch(yield from $this->preProcessMatch($response, $pattern))
            );
        } else {
            yield from $this->chatClient->postMessage(
                yield from $this->getMessageFromSearch($response)
            );
        }
    }

    private function preProcessMatch(Response $response, string $pattern) : \Generator
    {
        if (preg_match('#/book\.[^.]+\.php$#', $response->getRequest()->getUri(), $matches)) {
            /** @var Response $classResponse */
            $classResponse = yield from $this->chatClient->request(self::MANUAL_URL_BASE . '/class.' . rawurlencode($pattern) . '.php');
            if ($classResponse->getStatus() != 404) {
                return $classResponse;
            }
        }

        return $response;
    }

    private function getMysqlMessage(): string {
        // See https://gist.github.com/MadaraUchiha/3881905
        return "[**Please, don't use `mysql_*` functions in new code**](http://bit.ly/phpmsql). "
             . "They are no longer maintained [and are officially deprecated](http://j.mp/XqV7Lp). "
             . "See the [**red box**](http://j.mp/Te9zIL)? Learn about [*prepared statements*](http://j.mp/T9hLWi) instead, "
             . "and use [PDO](http://php.net/pdo) or [MySQLi](http://php.net/mysqli) - "
             . "[this article](http://j.mp/QEx8IB) will help you decide which. If you choose PDO, "
             . "[here is a good tutorial](http://j.mp/PoWehJ).";
    }

    /**
     * @uses getFunctionDetails()
     * @uses getClassDetails()
     * @uses getBookDetails()
     * @uses getPageDetailsFromH2()
     * @param Response $response
     * @return string
     * @internal param string $pattern
     */
    private function getMessageFromMatch(Response $response): string {
        $doc = $this->getHTMLDocFromResponse($response);
        $url = $response->getRequest()->getUri();

        try {
            $details = preg_match('#/(book|class|function)\.[^.]+\.php$#', $url, $matches)
                ? $this->{"get{$matches[1]}Details"}($doc)
                : $this->getPageDetailsFromH2($doc);
            return sprintf("[ [`%s`](%s) ] %s", $details[0], $url, $details[1]);
        } catch (NoComprendeException $e) {
            return sprintf("That [manual page](%s) seems to be in a format I don't understand", $url);
        } catch (\Throwable $e) {
            return 'Something went badly wrong with that lookup... ' . $e->getMessage();
        }
    }

    /**
     * Get details for pages like http://php.net/manual/en/control-structures.foreach.php
     *
     * @used-by getMessageFromMatch()
     * @param \DOMDocument $doc
     * @return array
     */
    private function getPageDetailsFromH2(\DOMDocument $doc) : array
    {
        $h2Elements = $doc->getElementsByTagName("h2");
        if ($h2Elements->length < 1) {
            throw new NoComprendeException('No h2 elements in HTML');
        }

        $descriptionElements = (new \DOMXPath($doc))->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' para ')]");

        $symbol = $this->normalizeMessageContent($h2Elements->item(0)->textContent);
        $description = $descriptionElements->length > 0
            ? $this->normalizeMessageContent($descriptionElements->item(0)->textContent)
            : $symbol;

        return [$symbol, $description];
    }

    /**
     * @used-by getMessageFromMatch()
     * @param \DOMDocument $doc
     * @return array
     */
    private function getFunctionDetails(\DOMDocument $doc) : array
    {
        $h1Elements = $doc->getElementsByTagName("h1");
        if ($h1Elements->length < 1) {
            throw new NoComprendeException('No h1 elements in HTML');
        }

        $descriptionElements = (new \DOMXPath($doc))->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dc-title ')]");

        $name = $this->normalizeMessageContent($h1Elements->item(0)->textContent) . '()';
        $description = $descriptionElements->length > 0
            ? $this->normalizeMessageContent($descriptionElements->item(0)->textContent)
            : $name . ' function';

        return [$name, $description];
    }

    /**
     * @used-by getMessageFromMatch()
     * @param \DOMDocument $doc
     * @return array
     * @internal param string $pattern
     */
    private function getBookDetails(\DOMDocument $doc) : array
    {
        $h1Elements = $doc->getElementsByTagName("h1");
        if ($h1Elements->length < 1) {
            throw new NoComprendeException('No h1 elements in HTML');
        }

        $title = $this->normalizeMessageContent($h1Elements->item(0)->textContent);
        return [$title, $title . ' book'];
    }

    /**
     * @used-by getMessageFromMatch()
     * @param \DOMDocument $doc
     * @return array
     */
    private function getClassDetails(\DOMDocument $doc) : array
    {
        $h1Elements = $doc->getElementsByTagName("h1");
        if ($h1Elements->length < 1) {
            throw new NoComprendeException('No h1 elements in HTML');
        }

        $descriptionElements = (new \DOMXPath($doc))->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' para ')]");

        $title = $this->normalizeMessageContent($h1Elements->item(0)->textContent);
        $symbol = preg_match('/^\s*the\s+(\S+)\s+class\s*$/i', $title, $matches)
            ? $matches[1]
            : $title;
        $description = $descriptionElements->length > 0
            ? $this->normalizeMessageContent($descriptionElements->item(0)->textContent)
            : $title;

        return [$symbol, $description];
    }

    // Handle broken SO's chat MD
    private function normalizeMessageContent(string $message): string
    {
        return trim(preg_replace('/\s+/', ' ', $message));
    }

    private function getHTMLDocFromResponse(Response $response) : \DOMDocument
    {
        $internalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($response->getBody());

        libxml_use_internal_errors($internalErrors);

        return $dom;
    }

    private function getMessageFromSearch(Response $response): \Generator {
        try {
            $dom = $this->getHTMLDocFromResponse($response);

            /** @var \DOMElement $firstResult */
            $firstResult = $dom->getElementById("quickref_functions")->getElementsByTagName("li")->item(0);
            /** @var \DOMElement $anchor */
            $anchor = $firstResult->getElementsByTagName("a")->item(0);

            $response = yield from $this->chatClient->request(
                self::URL_BASE . $anchor->getAttribute("href")
            );

            return $this->getMessageFromMatch($response);
        } catch (\Throwable $e) {
            return 'Something went badly wrong with that lookup... ' . $e->getMessage();
        }
    }
}
