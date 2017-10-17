<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;

class CanIUse extends BasePlugin
{
    private const DOMAIN = 'http://caniuse.com';

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
        return 'CanIUse';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'A quick search tool for CanIUse, a browser comparability feature list for modern standards.';
    }

    /**
     * @inheritDoc
     */
    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint('CanIUse', [$this, 'getLink'], 'caniuse')
        ];
    }

    /**
     * Entry point for generating and returning a link from the command.
     * @param Command $command
     * @return Promise
     */
    public function getLink(Command $command): Promise
    {
        return $this->chatClient->postReply(
            $command,
            $this->generateLink(
                implode(' ', $command->getParameters())
            )
        );
    }

    /**
     * Generate a link for Can I Use, based on the search term.
     *
     * @param  string $searchFor The search term, if any.
     * @return string            A markdown formatted link for Can I Use.
     */
    private function generateLink(string $searchFor): string
    {
        $searchFor = trim($searchFor);

        $title = 'Can I Use - Support tables for HTML5, CSS3, etc';
        $url = self::DOMAIN;

        if (strlen($searchFor) > 0) {
            $title = sprintf('Can I Use Search: `%s`', $searchFor);
            $url = $this->generateSearchUri($searchFor);
        }

        return sprintf('[%s](%s)', $title, $url);
    }

    /**
     * Create a search URI for a given search term.
     *
     * @param  string $searchFor The search string.
     * @return string            The full search URI.
     */
    private function generateSearchUri(string $searchFor): string
    {
        return self::DOMAIN . '/' . rawurlencode($searchFor);
    }
}
