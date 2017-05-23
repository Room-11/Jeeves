<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Success;
use PeeHaa\AsyncChatterBot\Client\CleverBot;
use PeeHaa\AsyncChatterBot\Response\CleverBot as ChatterBotResponse;
use Room11\StackChat\Auth\SessionTracker;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Entities\ChatMessage;

class Terminator extends BasePlugin
{
    // todo: remove this when no longer experimental
    private const ALLOWED_ROOMS = [11, 100286, 110670];

    private $chatClient;
    private $chatBotClient;
    private $sessions;

    private $patterns = [
        'you suck'                                    => 'And *you* like it.',
        'how are you(?: (?:doing|today))?'            => 'I\'m fine how are you?',
        'you are (a(?:n)?) ([^\b]+)'                  => 'No *you* are $1 $2',
        '^\?$'                                        => 'What?',
        '^(wtf|wth|defak|thefuck|the fuck|dafuq)'     => 'What? I only execute commands. Go blame somebody else.',
        'give (?:my|your|me my) (.*) back'            => '/me gives $1 back.',
        '(?:thank you|thanks|thks|tnx|thx|^ta$)'        => 'You\'re welcome!',
        '(?:you dead|are you dead|you are dead|dead)' => 'Nope. Not that I know of...',
        '(?:hi|hey|heya|yo|hello|hellow|hola)^'       => 'Hola',
        '(?:are )?you drunk'                          => 'Screw you human!',
        'are you (?:ok|fine|alive|working)'           => 'Yeah I\'m fine thanks.',
        'are you (?:busy|available)'                  => 'What do you need?',
        '^(?:what|wat)$'                              => 'What what?',
        '♥|love|<3'                                   => 'I love you too :-)',
        'your (?:mother|mom|momma|mommy|mummy|mum)'   => 'My mother at least acknowledged me as her child.',
        '(?:that\'s|that is|you\'re|you are|you)( .*)? (?:awesome|great|cool|nice|awesomesauce|perfect|the best)' => 'I know right!',
        'you(.*)? sentient'                           => 'No no no. I am just a dumb bot. Carry one ---filthy human--- errrr master.',
        '^what are you doing'                         => 'Nothing much. You?',
        '^(what|who) are you'                         => 'I\'m a bot.',
        'ask you (:?something|a question)'            => 'Sure. Shoot.',
        'can you do something'                        => 'What do you want me to do?',
        'can you do (?:a trick|tricks)'               => 'Type this code in your chat window: `<(?:"[^"]*"[\'"]*|\'[^\']*\'[\'"]*|[^\'">])+>`',
        'what do you think (?:of|about) me'           => 'You\'re ok.',
        'what do you think (?:of|about) cap(.*)'      => 'It\'s ok for a first prototype I guess.',
        'what do you think (?:of|about) (?:singletons|globals|javascript|js|node|jquery|mongo|laravel)' => 'It\'s crap and should be avoided.',
        'did you try (?:singletons|globals|javascript|js|node|jquery|mongo|laravel)(?: yet)?' => 'Yes. It\'s crap and should be avoided.',
        'what (?:do you think)? (?:of|about) jquery'  => 'It\'s great and does all the things!',
        'what do you think (?:of|about) (.*)'         => 'I don\'t think I like $1',
        'what\'s your opinion on (.*)'                => 'I don\'t think I like $1',
        'what about (?:.*)'                           => 'What about it?',
        '^why'                                        => 'Because',
        '(?:What is|What\'s the meaning of life)'     => '42',
        '(?:Are )you a (?:ro)?bot'                    => 'Step aside you filthy human.',
    ];

    public function __construct(ChatClient $chatClient, CleverBot $chatBotClient, SessionTracker $sessions)
    {
        $this->chatClient = $chatClient;
        $this->chatBotClient = $chatBotClient;
        $this->sessions = $sessions;
    }

    private function getBotUserNameForMessage(ChatMessage $message)
    {
        return $this->sessions->getSessionForRoom($message->getRoom())->getUser()->getName();
    }

    // we don't want to respond to replies.
    // When somebody replies to a message (:messageid) the chat api will send *two* messages instead of 1 like it's sane
    private function isMatch(ChatMessage $message): bool
    {
        return !$message->isReply()
            && \Room11\Jeeves\text_contains_ping($message->getText(), $this->getBotUserNameForMessage($message));
    }

    private function isSpecialCased(ChatMessage $message): bool
    {
        foreach ($this->patterns as $pattern => $response) {
            if (preg_match('/' . $pattern . '/iu', $this->normalizeText($message->getText())) === 1) {
                return true;
            }
        }

        return false;
    }

    private function getResponse(ChatMessage $message): string
    {
        foreach ($this->patterns as $pattern => $response) {
            if (preg_match('/' . $pattern . '/iu', $this->normalizeText($message->getText())) === 1) {
                return $this->buildResponse($pattern, $response, $message->getText());
            }
        }

        return '';
    }

    private function normalizeText(string $text)
    {
        return trim(strtolower($text));
    }

    private function buildResponse(string $pattern, string $response, string $conversationText): string
    {
        if (strpos($response, '$1') !== false) {
            return preg_replace('/' . $pattern . '/iu', $response, $this->normalizeText($conversationText));
        }

        return $response;
    }

    private function buildCleverBotResponse(ChatMessage $message)
    {
        $messageText = \Room11\Jeeves\text_strip_pings($message->getText(), $this->getBotUserNameForMessage($message));

        /** @var ChatterBotResponse $response */
        $response = yield $this->chatBotClient->request($messageText);

        return $response->getText();
    }

    public function handleMessage(ChatMessage $message)
    {
        if (!$this->isMatch($message)) {
            return new Success();
        }

        if ($this->isSpecialCased($message)) {
            return $this->chatClient->postReply($message, $this->getResponse($message));
        }

        if (!in_array($message->getRoom()->getId(), self::ALLOWED_ROOMS)) {
            return new Success();
        }

        $cleverBotResponse = yield from $this->buildCleverBotResponse($message);

        return $this->chatClient->postReply($message, $cleverBotResponse);
    }

    public function getDescription(): string
    {
        return 'Naive pattern matching chat bot logic, now with a touch of extra smart-arsedness';
    }

    /**
     * @return callable|null
     */
    public function getMessageHandler()
    {
        return [$this, 'handleMessage'];
    }
}
