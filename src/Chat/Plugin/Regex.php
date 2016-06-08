<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\AutoName;
use Room11\Jeeves\Chat\Plugin\Traits\CommandOnly;
use Room11\Jeeves\Chat\Plugin\Traits\Helpless;
use Room11\Jeeves\Chat\PluginCommandEndpoint;

class Regex implements Plugin
{
    use CommandOnly, AutoName, Helpless;

    const HE_COMES = "\x48\xCD\xA8\xCD\x8A\xCC\xBD\xCC\x85\xCC\xBE\xCC\x8E\xCC\xA1\xCC\xB8\xCC\xAA\xCC\xAF\x45\xCC\xBE"
                   . "\xCD\x9B\xCD\xAA\xCD\x84\xCC\x80\xCC\x81\xCC\xA7\xCD\x98\xCC\xAC\xCC\xA9\x20\xCD\xA7\xCC\xBE\xCD"
                   . "\xAC\xCC\xA7\xCC\xB6\xCC\xA8\xCC\xB1\xCC\xB9\xCC\xAD\xCC\xAF\x43\xCD\xAD\xCC\x8F\xCD\xA5\xCD\xAE"
                   . "\xCD\x9F\xCC\xB7\xCC\x99\xCC\xB2\xCC\x9D\xCD\x96\x4F\xCD\xAE\xCD\x8F\xCC\xAE\xCC\xAA\xCC\x9D\xCD"
                   . "\x8D\x4D\xCD\x8A\xCC\x92\xCC\x9A\xCD\xAA\xCD\xA9\xCD\xAC\xCC\x9A\xCD\x9C\xCC\xB2\xCC\x96\x45\xCC"
                   . "\x91\xCD\xA9\xCD\x8C\xCD\x9D\xCC\xB4\xCC\x9F\xCC\x9F\xCD\x99\xCC\x9E\x53\xCD\xAF\xCC\xBF\xCC\x94"
                   . "\xCC\xA8\xCD\x80\xCC\xA5\xCD\x85\xCC\xAB\xCD\x8E\xCC\xAD";

    private $chatClient;

    public function __construct(ChatClient $chatClient) {
        $this->chatClient = $chatClient;
    }

    private function hasPattern(\DOMDocument $dom): bool {
        return (bool) $dom->getElementsByTagName('code')->length;
    }

    private function zalgo(\DOMDocument $dom): bool {
        return (bool) @preg_match('/<[^>]*\[\^[^\]]*>.*\\]/', $dom->getElementsByTagName('code')->item(0)->textContent);
    }

    private function doesMatch(\DOMDocument $dom): bool {
        return (bool) @preg_match($dom->getElementsByTagName('code')->item(0)->textContent, $this->getSubject($dom));
    }

    private function hasCapturingGroups(\DOMDocument $dom): bool {
        @preg_match($dom->getElementsByTagName('code')->item(0)->textContent, $this->getSubject($dom), $matches);

        return count($matches) > 1;
    }

    private function getCapturingGroups(\DOMDocument $dom): string {
        @preg_match($dom->getElementsByTagName('code')->item(0)->textContent, $this->getSubject($dom), $matches);

        return "[ " . implode(" , ", array_slice($matches, 1)) . " ]";
    }

    private function getSubject(\DOMDocument $dom): string {
        $clonedDom = clone $dom;

        $pattern = $clonedDom->getElementsByTagName('code')->item(0);
        $parent  = $pattern->parentNode;

        $parent->removeChild($pattern);

        $subject  = "";

        foreach ($parent->childNodes as $child) {
            $subject .= $parent->ownerDocument->saveHTML($child);
        }

        return $subject;
    }

    public function test(Command $command): Promise {
        if (!$command->hasParameters()) {
            return new Success();
        }

        $dom = new \DOMDocument();
        $dom->loadHTML(implode(" ", $command->getParameters()));

        if (!$this->hasPattern($dom)) {
            return $this->chatClient->postReply($command, 'Pattern must be wrapped in a code block');
        }

        if ($this->zalgo($dom)) {
            return $this->chatClient->postReply($command, self::HE_COMES);
        }

        if (!$this->doesMatch($dom)) {
            return $this->chatClient->postReply($command, 'No match');
        }

        if (!$this->hasCapturingGroups($dom)) {
            return $this->chatClient->postReply($command, 'Matches \o/');
        }

        return $this->chatClient->postReply(
            $command,
            "Matches with the following captured groups: " . $this->getCapturingGroups($dom)
        );
    }

    public function getDescription(): string
    {
        return 'Evaluates regular expressions with PCRE';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Test', [$this, 'test'], 'regex')];
    }
}
