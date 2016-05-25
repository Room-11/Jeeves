<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;

class CanIUse implements Plugin
{
    use CommandOnlyPlugin;

    const DOMAIN = 'http://caniuse.com';

    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    private function getResult(Command $command): \Generator
    {
        yield from $this->chatClient->postReply($command, $this->generateLink(implode(' ', $command->getParameters())));
    }

    /**
     * Generate a link for Can I Use, based on the search term.
     *
     * @param  string $searchFor The search term, if any.
     * @return string            A markdown formatted link for Can I Use.
     */
    private function generateLink(string $searchFor = ''): string
    {
        $searchFor = trim($searchFor);

        $title = 'Can I Use - Support tables for HTML5, CSS3, etc';
        $url   = self::DOMAIN;

        if (strlen($searchFor) > 0) {
            $title = sprintf('Can I Use - `%s`', ucwords($searchFor));
            $url   = $this->generateSearchUri($searchFor);
        }

        return sprintf('[%s](%s)', $title, $url);
    }

    /**
     * Create a search URI for a given search term.
     *
     * @param  string $searchFor The search string.
     * @return string            The full search URI.
     */
    private function generateSearchUri(string $searchFor)
    {
        return self::DOMAIN . '/' . urlencode($searchFor);
    }

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator
    {
        yield from $this->getResult($command);
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getHandledCommands(): array
    {
        return ['caniuse', 'ciu'];
    }
}
