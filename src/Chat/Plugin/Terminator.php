<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Conversation;
use Room11\Jeeves\Chat\Message\Message;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\NoCommands;
use Room11\Jeeves\Chat\Plugin\Traits\NoDisableEnable;
use Room11\Jeeves\Chat\Plugin\Traits\NoEventHandlers;

class Terminator implements Plugin
{
    use NoCommands, NoEventHandlers, NoDisableEnable;

    const COMMAND = 'terminator';

    private $chatClient;

    private $patterns = [
        'you suck'                                    => 'And you *like* it',
        'how are you( (doing|today))?'                => 'I\'m fine how are you?',
        'you are a ([^\b]+)'                          => 'No *you* are a $i',
        '^\?$'                                        => 'What?',
        '^(wtf|wth|defak|thefuck|the fuck|dafuq)$'    => 'What? I only execute commands. Go blame somebody else.',
        'give (:?my|your|me my) (.*) back'            => '/me gives $1 back',
        '(:?thank you|thanks|thks|tnx)'               => 'You\'re welcome',
        '(:?you dead|are you dead|you are dead|dead)' => 'Nope. Not that I know of',
        '(:?hi|hey|yo|hello|hellow|hola)^'            => 'Hola',
        '(:?are )?you drunk'                          => 'Screw you human!',
        '^(:?what|wat)$'                              => 'What what?',
        '♥|love|<3'                                   => 'I love you too',
        'your (mother|mom|momma|mommy|mummy)'         => 'My mother at least acknowledged me as her child',
        '(That\'s|That is|You\'re|You are)( .*)? awesome|great|cool|nice|awesomesauce|perfect' => 'I know right!',
    ];

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    private function isMatch(Conversation $conversation): bool
    {
         foreach ($this->patterns as $pattern => $response) {
            if (preg_match('/' . $pattern . '/u', $this->normalizeText($conversation->getText())) === 1) {
                return true;
            }
        }

        return false;
    }

    private function getResponse(Conversation $conversation): string
    {
        foreach ($this->patterns as $pattern => $response) {
            if (preg_match('/' . $pattern . '/u', $this->normalizeText($conversation->getText())) === 1) {
                return $response;
            }
        }
    }

    private function normalizeText(string $text)
    {
        return trim(strtolower($text));
    }

    public function handleMessage(Message $message): Promise
    {
        return $message instanceof Conversation && $this->isMatch($message)
            ? $this->chatClient->postReply($message, $this->getResponse($message))
            : new Success();
    }

    public function getName(): string
    {
        return 'Terminator';
    }

    public function getDescription(): string
    {
        return 'Naive pattern matching chat bot logic';
    }

    public function getHelpText(array $args): string
    {
        // TODO: Implement getHelpText() method.
    }

    /**
     * @return callable|null
     */
    public function getMessageHandler()
    {
        return [$this, 'handleMessage'];
    }
}
