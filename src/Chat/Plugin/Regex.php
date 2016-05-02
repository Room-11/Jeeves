<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;

class Regex implements Plugin
{
    use CommandOnlyPlugin;

    private $chatClient;

    public function __construct(ChatClient $chatClient) {
        $this->chatClient = $chatClient;
    }

    private function getResult(Command $command): \Generator {
        $dom = new \DOMDocument();
        $dom->loadHTML(implode(" ", $command->getParameters()));

        if (!$this->hasPattern($dom)) {
            yield from $this->chatClient->postMessage(
                "Pattern must be wrapped in a code block."
            );

            return;
        }

        if ($this->zalgo($dom)) {
            yield from $this->chatClient->postMessage(
                "H̸̡̪̯ͨ͊̽̅̾̎Ȩ̬̩̾͛ͪ̈́̀́͘ ̶̧̨̱̹̭̯ͧ̾ͬC̷̙̲̝͖ͭ̏ͥͮ͟Oͮ͏̮̪̝͍M̲̖͊̒ͪͩͬ̚̚͜Ȇ̴̟̟͙̞ͩ͌͝S̨̥̫͎̭ͯ̿̔̀ͅ"
            );

            return;
        }

        if (!$this->doesMatch($dom)) {
            yield from $this->chatClient->postMessage(
                "No match."
            );

            return;
        }

        if (!$this->hasCapturingGroups($dom)) {
            yield from $this->chatClient->postMessage(
                "Matches \\o/"
            );

            return;
        }

        yield from $this->chatClient->postMessage(
            "Matches with the following captured groups: " . $this->getCapturingGroups($dom)
        );
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

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator
    {
        if (!$command->getParameters()) {
            return;
        }

        yield from $this->getResult($command);
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getHandledCommands(): array
    {
        return ["regex", "re", "pcre"];
    }
}
