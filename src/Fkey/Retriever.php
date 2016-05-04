<?php declare(strict_types=1);

namespace Room11\Jeeves\Fkey;

use Amp\Artax\Client as HttpClient;

class Retriever
{
    private $httpClient;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function get(string $url): FKey
    {
        $promise = $this->httpClient->request($url);

        $response = \Amp\wait($promise);

        return new FKey($this->getFromHtml($response->getBody()));
    }

    private function getFromHtml(string $html): string
    {
        $dom = new \DOMDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors($internalErrors);

        foreach ($dom->getElementsByTagName('input') as $inputNode) {
            if (!$inputNode->hasAttribute('name') || $inputNode->getAttribute('name') !== 'fkey') {
                continue;
            }

            return $inputNode->getAttribute('value');
        }

        throw new NotFoundException();
    }
}
