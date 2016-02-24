<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Command\Command;
use Room11\Jeeves\Chat\Command\Message;

class Regex implements Plugin
{
    const COMMANDS = ["regex", "re", "pcre"];

    private $chatClient;

    public function __construct(ChatClient $chatClient) {
        $this->chatClient = $chatClient;
    }

    public function handle(Message $message): \Generator {
        if (!$this->validMessage($message)) {
            return;
        }

        yield from $this->getResult($message);
    }

    private function validMessage(Message $message): bool {
        return $message instanceof Command
        && in_array($message->getCommand(), self::COMMANDS, true)
        && $message->getParameters();
    }

    private function getResult(Message $message): \Generator {
        $dom = new \DOMDocument();
        $dom->loadHTML(implode(" ", $message->getParameters()));

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
        return (bool) @preg_match($dom->getElementsByTagName('code')->item(0)->textContent, '/<[^>]*\\[\\^[^\\]]*>.*\\]/');
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
}
