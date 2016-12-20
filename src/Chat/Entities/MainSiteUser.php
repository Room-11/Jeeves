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
    private $githubUsername;

    public static function createFromDOMDocument(\DOMDocument $doc): MainSiteUser // very todo: remove horrible static ctor
    {
        $xpath = new \DOMXPath($doc);

        $twitterLink = $xpath->query("//li[span[" . xpath_html_class('icon-twitter') . "]]/a");
        $twitterHandle = $twitterLink->length > 0
            ? trim($twitterLink->item(0)->textContent)
            : null;

        // cannot separate this because of static, bloody mancs. 
        $githubLink = $xpath->query("//li[span[" . xpath_html_class('icon-github') . "]]/a");
        $githubUsername = $githubLink->length > 0
            ? trim($githubLink->item(0)->textContent)
            : null;

        return new MainSiteUser($twitterHandle, $githubUsername);
    }

    public function __construct(string $twitterHandle = null, string $githubUsername = null)
    {
        $this->twitterHandle = $twitterHandle;
        $this->githubUsername = $githubUsername;
    }

    /**
     * @return string
     */
    public function getTwitterHandle()
    {
        return $this->twitterHandle;
    }

    public function getGithubUsername()
    {
        return $this->githubUsername;
    }
}
