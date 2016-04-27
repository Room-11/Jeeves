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

    private $chatClient;

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
        $pattern = str_replace('::', '.', implode(' ', $message->getParameters()));

        if (substr($pattern, 0, 6) === "mysql_") {
            yield from $this->chatClient->postMessage(
                $this->getMysqlMessage()
            );

            return;
        }

        $url = "http://php.net/manual-lookup.php?scope=quickref&pattern=" . rawurlencode($pattern);

        /** @var Response $response */
        $response = yield from $this->chatClient->request($url);

        if ($response->getPreviousResponse() !== null) {
            yield from $this->chatClient->postMessage(
                $this->getMessageFromMatch($response)
            );
        } else {
            yield from $this->chatClient->postMessage(
                yield from $this->getMessageFromSearch($response)
            );
        }
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
     */
    private function getMessageFromMatch(Response $response): string {
        $dom = $this->getHTMLDocFromResponse($response);
        $xpath = new \DOMXPath($dom);

        $url = $response->getRequest()->getUri();

        try {
            $details = preg_match('#/(book|class|function)\.[^.]+\.php$#', $url, $matches)
                ? $this->{"get{$matches[1]}Details"}($dom, $xpath)
                : $this->getPageDetailsFromH2($dom, $xpath);
            return sprintf("[ [`%s`](%s) ] %s", $details[0], $url, $details[1]);
        } catch (NoComprendeException $e) {
            return sprintf("That [manual page](%s) seems to be in a format I don't understand", $url);
        } catch (\Exception $e) {
            return 'Something went badly wrong with that lookup... ' . $e->getMessage();
        }
    }

    /**
     * Get details for pages like http://php.net/manual/en/control-structures.foreach.php
     *
     * @used-by getMessageFromMatch()
     * @param \DOMDocument $doc
     * @param \DOMXPath $xpath
     * @return array
     */
    private function getPageDetailsFromH2(\DOMDocument $doc, \DOMXPath $xpath) : array
    {
        $h2Elements = $doc->getElementsByTagName("h2");
        if ($h2Elements->length < 1) {
            throw new NoComprendeException('No h2 elements in HTML');
        }

        $descriptionElements = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' para ')]");

        $symbol = $this->normalizeMessageContent($h2Elements->item(0)->textContent);
        $description = $descriptionElements->length > 0
            ? $this->normalizeMessageContent($descriptionElements->item(0)->textContent)
            : $symbol;

        return [$symbol, $description];
    }

    /**
     * @used-by getMessageFromMatch()
     * @param \DOMDocument $doc
     * @param \DOMXPath $xpath
     * @return array
     */
    private function getFunctionDetails(\DOMDocument $doc, \DOMXPath $xpath) : array
    {
        $h1Elements = $doc->getElementsByTagName("h1");
        if ($h1Elements->length < 1) {
            throw new NoComprendeException('No h1 elements in HTML');
        }

        $descriptionElements = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dc-title ')]");

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
     * @throws NoComprendeException
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
     * @param \DOMXPath $xpath
     * @return array
     */
    private function getClassDetails(\DOMDocument $doc, \DOMXPath $xpath) : array
    {
        $h1Elements = $doc->getElementsByTagName("h1");
        $descriptionElements = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' para ')]");

        if ($h1Elements->length < 1) {
            throw new NoComprendeException('No h1 elements in HTML');
        }

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
        $dom = $this->getHTMLDocFromResponse($response);

        /** @var \DOMElement $firstResult */
        $firstResult = $dom->getElementById("quickref_functions")->getElementsByTagName("li")->item(0);
        /** @var \DOMElement $anchor */
        $anchor = $firstResult->getElementsByTagName("a")->item(0);

        $response = yield from $this->chatClient->request(
            "https://php.net" . $anchor->getAttribute("href")
        );

        return $this->getMessageFromMatch($response);
    }
}
