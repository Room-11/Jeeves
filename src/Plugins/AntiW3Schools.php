<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Message;

class AntiW3Schools extends BasePlugin
{

    const BAD_HOST_PATTERN = 'w3schools\.com';
    const W3S_CATEGORY_RESPONSES = [
        'html' => '[Check the Mozilla Developer Network HTML documentation](https://developer.mozilla.org/docs/Web/HTML) for help with HTML.',
        'css' => '[Check the Mozilla Developer Network CSS documentation](https://developer.mozilla.org/docs/Web/CSS) for help with CSS.',
        'js' => '[Check the Mozilla Developer Network JS documentation](https://developer.mozilla.org/docs/Web/JavaScript) for help with JavaScript.',
        'sql' => '[Check MySQL\'s official MySQL documentation](https://dev.mysql.com/doc/) for more information.',
        'php' => '[Check the official PHP Documentation](https://secure.php.net/docs.php) for help with PHP.',
        'bootstrap' => '[Check the official Bootstrap Documentation](https://getbootstrap.com/getting-started/) for help with the Bootstrap framework.',
        'jquery' => '[Check the official jQuery API Documentation](https://api.jquery.com/) for help with the jQuery library.',
        'angular' => '[Check the official AngularJS API Documentation](https://docs.angularjs.org/api) for help with the AngularJS framework.',
        'xml'=> '[Check W3\'s documentation on XML](https://www.w3.org/standards/xml/core) for more information.',
        'ajax' => '[Check the Mozilla Developer Network AJAX documentation](https://developer.mozilla.org/docs/AJAX) for more information.',
    ];

    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'AntiW3Schools';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'A small monitoring plugin which bothers people when a w3schools link is detected.';
    }

    /**
     * @inheritDoc
     */
    public function getMessageHandler(): array
    {
        return [$this, 'handleMessage'];
    }

    /**
     * Entry point for checking a message for w3schools links.
     * @param Message $message
     * @return Promise
     */
    public function handleMessage(Message $message): Promise
    {
        return $this->isSuitable($message) && $this->containsTerribleUri($message->getText())
            ? $this->chatClient->postReply($message, $this->createReply($message))
            : new Success();
    }

    /**
     * Check whether a message is initially suitable for a response from this plugin.
     * @param Message $message
     * @return bool
     */
    private function isSuitable(Message $message) {
        return $message->getType() === Message::TYPE_NEW;
    }

    /**
     * Check if text contains a W3C link.
     * @param string $text
     * @return bool
     */
    private function containsTerribleUri(string $text) : bool
    {
        return preg_match('#' . self::BAD_HOST_PATTERN . '#i', $text) === 1;
    }

    /**
     * Create a suitable message to bother people about W3C.
     * @param Message $message
     * @return string
     */
    private function createReply(Message $message) : string
    {
        $return = 'W3Schools should not be trusted as a reliable resource. [Click here to read why](http://www.w3fools.com/).';

        $categories = $this->dissectCategoriesFromString($message->getText());

        foreach($categories as $category) {
            $return .= ' ' . self::W3S_CATEGORY_RESPONSES[$category];
        }

        return $return;

    }

    /**
     * Retrieve W3S categories from a given string.
     * @param string $text
     * @return string[]
     */
    private function dissectCategoriesFromString(string $text) {

        // Extract the category from all W3S URIs found.
        $categoryNames = array_keys(self::W3S_CATEGORY_RESPONSES);

        $matchSets = [];
        $categoryMatchPattern = '#' . self::BAD_HOST_PATTERN . '/(' . implode('|', $categoryNames) . ')/#i';
        $matchResult = preg_match_all($categoryMatchPattern, $text, $matchSets);

        if($matchResult === 0 || $matchResult === false) {
            // If no matches were found (or the regex failed), return no results.
            return [];
        }

        // Prevent multiple messages for the same category by only returning one of each type found.
        return array_unique(
            array_map(
                'strtolower',
                $matchSets[1]
            )
        );
    }
}
