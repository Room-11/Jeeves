<?php declare(strict_types=1);

namespace Room11\Jeeves\Fkey;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;

class Retriever
{
    private $httpClient;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function get(string $url): FKey
    {
        /** @var HttpResponse $response */
        $response = \Amp\wait($this->httpClient->request($url));

        return new FKey($this->getFromHtml($response->getBody()));
    }

    private function getFromHtml(string $html): string
    {
        $dom = new \DOMDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors($internalErrors);

        /** @var \DOMElement $inputNode */
        foreach ($dom->getElementsByTagName('input') as $inputNode) {
            if (!$inputNode->hasAttribute('name') || $inputNode->getAttribute('name') !== 'fkey') {
                continue;
            }

            return $inputNode->getAttribute('value');
        }

        throw new NotFoundException();
    }
}
