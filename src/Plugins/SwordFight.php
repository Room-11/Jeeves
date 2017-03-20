<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Amp\Success;
use Room11\StackChat\Auth\SessionTracker;
use Room11\StackChat\Client\Client;
use Room11\StackChat\Entities\ChatMessage;

class SwordFight extends BasePlugin
{
    private const MINIMUM_MATCH_PERCENTAGE = 60;

    private $chatClient;
    private $sessions;

    // We only match on insults for now because comparing text is actually pretty hard and I'm lazy
    private $matches = [
        'You fight like a Dairy Farmer!' => 'How appropriate! You fight like a cow!',
        'This is the END for you, you gutter crawling cur!' => 'And I\'ve got a little TIP for you, get the POINT?',
        'I\'ve spoken with apes more polite than you!' => 'I\'m glad to hear you attended your family reunion!',
        'Soon you\'ll be wearing my sword like a shish kebab!' => 'First you better stop waving it about like a feather duster.',
        'People fall at my feet when they see me coming!' => 'Even BEFORE they smell your breath?',
        'I\'m not going to take your insolence sitting down!' => 'Your hemorroids are flaring up again eh?',
        'I once owned a dog that was smarter than you.' => 'He must have taught you everything you know.',
        'Nobody\'s ever drawn blood from me and nobody ever will.' => 'You run THAT fast?',
        'Have you stopped wearing diapers yet?' => 'Why? Did you want to borrow one?',
        'There are no words for how disgusting you are.' => 'Yes there are. You just never learned them.',
        'You make me want to puke.' => 'You make me think somebody already did.',
        'My handkerchief will wipe up your blood!' => 'So you got that job as janitor, after all.',
        'I got this scar on my face during a mighty struggle!' => 'I hope now you\'ve learned to stop picking your nose.',
        'I\'ve heard you are a contemptible sneak.' => 'Too bad no one\'s ever heard of YOU at all.',
        'You\'re no match for my brains, you poor fool.' => 'I\'d be in real trouble if you ever used them.',
        'You have the manners of a beggar.' => 'I wanted to make sure you\'d feel comfortable with me.',
        'Now I know what filth and stupidity really are.' => 'I\'m glad to hear you attended your family reunion.',
        'Every word you say to me is stupid.' => 'I wanted to make sure you\'d feel comfortable with me.',
        'I\'ve got a long, sharp lesson for you to learn today.' => 'And I\'ve got a little TIP for you. Get the POINT?',
        'I will milk every drop of blood from your body!' => 'How appropriate, you fight like a cow!',
        'I\'ve got the courage and skill of a master swordsman.' => 'I\'d be in real trouble if you ever used them.',
        'My tongue is sharper than any sword' => 'First, you\'d better stop waving it like a feather-duster.',
        'My name is feared in every dirty corner of this island!' => 'So you got that job as a janitor, after all.',
        'My wisest enemies run away at the first sight of me!' => 'Even BEFORE they smell your breath?',
        'Only once have I met such a coward!' => 'He must have taught you everything you know.',
        'If your brother\'s like you, better to marry a pig.' => 'You make me think somebody already did.',
        'No one will ever catch ME fighting as badly as you do.' => 'You run THAT fast?',
        'My last fight ended with my hands covered with blood.' => 'I hope now you\'ve learned to stop picking your nose.',
        'I hope you have a boat ready for a quick escape.' => 'I hope you have a boat ready for a quick escape.',
        'My sword is famous all over the Caribbean!' => 'Too bad no one\'s ever heard of YOU at all.',
        'You are a pain in the backside, sir!' => 'Your hemorrhoids are flaring up again, eh?',
        'There are no clever moves that can help you now.' => 'Yes there are. You just never learned them.',
        'Every enemy I\'ve met I\'ve annihilated!' => 'With your breath, I\'m sure they all suffocated.',
        'You\'re as repulsive as a monkey in a negligee.' => 'I look THAT much like your fiancée?',
        'Killing you would be justifiable homicide!' => 'Then killing you must be justifiable fungicide.',
        'You\'re the ugliest monster ever created!' => 'If you don\'t count all the ones you\'ve dated.',
        'I\'ll skewer you like a sow at a buffet!' => 'When I\'m done with you, you\'ll be a boneless filet.',
        'Would you like to be buried, or cremated?' => 'With you around, I\'d prefer to be fumigated.',
        'Coming face to face with me must leave you petrified!' => 'Is that your face? I thought it was your backside.',
        'When your father first saw you, he must have been mortified!' => 'At least mine can be identified.',
        'You can\'t match my witty repartee!' => 'I could, if you would use some breath spray.',
        'I have never seen such clumsy swordplay!' => 'You would have, but you were always running away.',
        'En garde! Touché!' => 'Oh, that is so cliché.',
        'Throughout the Caribbean, my great deeds are celebrated!' => 'Too bad they\'re all fabricated.',
        'I can\'t rest \'til you\'ve been exterminated!' => 'Then perhaps you should switch to decaffeinated.',
        'I\'ll leave you devasted, mutilated, and perforated!' => 'Your odor alone makes me aggravated, agitated, and infuriated.',
        'Heaven preserve me! You look like something that\'s died!' => 'The only way you\'ll be preserved is in formaldehyde.',
        'I\'ll hound you night and day!' => 'Then be a good dog. Sit! Stay!',
    ];

    public function __construct(Client $chatClient, SessionTracker $sessions)
    {
        $this->chatClient = $chatClient;
        $this->sessions = $sessions;
    }

    private function isMatch(ChatMessage $message): bool
    {
        $botUserName = $this->sessions->getSessionForRoom($message->getRoom()->getIdentifier())->getUser()->getName();
        $messageText = $message->getText();

        if (!($message->isReply() || \Room11\Jeeves\text_contains_ping($messageText, $botUserName))) {
            return false;
        }

        foreach ($this->matches as $insult => $response) {
            if ($this->getMatchingPercentage($insult, $messageText) >= self::MINIMUM_MATCH_PERCENTAGE) {
                return true;
            }
        }

        return false;
    }

    private function getMatchingPercentage(string $insult, string $text): float
    {
        similar_text($this->normalize($text), $this->normalize($insult), $percentage);

        return $percentage;
    }

    private function normalize(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/@[^\b\s]+/', '', $text);
        $text = preg_replace('/[^a-z0-9 ]/', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function getResponse(ChatMessage $message): string
    {
        $bestMatchPercentage = 0;
        $bestMatchResponse   = null;

        foreach ($this->matches as $insult => $response) {
            $matchPercentage = $this->getMatchingPercentage($insult, $message->getText());

            if ($matchPercentage > $bestMatchPercentage) {
                $bestMatchPercentage = $matchPercentage;
                $bestMatchResponse   = $response;
            }
        }

        if ($bestMatchResponse === null) {
            throw new \LogicException('Could not get a match response!');
        }

        return (string)$bestMatchResponse;
    }

    public function handleMessage(ChatMessage $message): Promise
    {
        return $this->isMatch($message)
            ? $this->chatClient->postReply($message, $this->getResponse($message))
            : new Success();
    }

    public function getDescription(): string
    {
        return 'Trades insults in conversation';
    }

    /**
     * @return callable|null
     */
    public function getMessageHandler()
    {
        return [$this, 'handleMessage'];
    }
}
