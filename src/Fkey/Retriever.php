<?php declare(strict_types=1);

namespace Room11\Jeeves\Fkey;

class Retriever
{
    public function getFromHtml(string $html): FKey
    {
        $dom = new \DOMDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors($internalErrors);

        return $this->getFromDOMDocument($dom);
    }

    public function getFromDOMDocument(\DOMDocument $dom): FKey
    {
        /** @var \DOMElement $inputNode */
        foreach ($dom->getElementsByTagName('input') as $inputNode) {
            if (!$inputNode->hasAttribute('name') || $inputNode->getAttribute('name') !== 'fkey') {
                continue;
            }

            return new FKey($inputNode->getAttribute('value'));
        }

        throw new NotFoundException();
    }
}
