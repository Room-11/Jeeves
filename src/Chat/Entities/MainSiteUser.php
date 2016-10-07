<?php declare(strict_types = 1);
/**
 * Created by PhpStorm.
 * User: chris.wright
 * Date: 07/10/2016
 * Time: 19:55
 */

namespace Room11\Jeeves\Chat\Entities;

use function Room11\DOMUtils\xpath_html_class;

class MainSiteUser
{
    // todo: write this whole class :-P

    /**
     * @var string
     */
    private $twitterHandle;

    public static function createFromDOMDocument(\DOMDocument $doc): MainSiteUser // very todo: remove horrible static ctor
    {
        $xpath = new \DOMXPath($doc);

        $twitterLink = $xpath->query("//li[span[" . xpath_html_class('icon-twitter') . "]]/a");
        $twitterHandle = $twitterLink->length > 0
            ? trim($twitterLink->item(0)->textContent)
            : null;

        return new MainSiteUser($twitterHandle);
    }

    public function __construct(string $twitterHandle = null)
    {
        $this->twitterHandle = $twitterHandle;
    }

    /**
     * @return string
     */
    public function getTwitterHandle()
    {
        return $this->twitterHandle;
    }
}