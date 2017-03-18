<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Promise;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client;
use function Room11\Jeeves\dateinterval_to_string;

class Rebecca extends BasePlugin
{
    private const FRIDAY_VIDEO_URL = 'https://www.youtube.com/watch?v=kfVsfOSbJY0';
    private const SATURDAY_VIDEO_URL = 'https://www.youtube.com/watch?v=GVCzdpagXOQ';

    private $chatClient;

    public function __construct(Client $chatClient) {
        $this->chatClient = $chatClient;
    }

    public function gottaGetDownOnFriday(Command $command): Promise
    {
        return $this->chatClient->postReply($command, $this->getResponse());
    }

    private function getResponse(): string
    {
        switch (date('l')) {
            case 'Thursday':
                return "Happy Prebeccaday!";
            case 'Friday':
                return self::FRIDAY_VIDEO_URL;
            case 'Saturday':
                return sprintf('[Today is Saturday. And Sunday comes afterwards.](%s)', self::SATURDAY_VIDEO_URL);
            default:
                return $this->getCountdown();
        }
    }

    private function getCountdown(): string
    {
        $timeLeft = $this->getTimeUntilNextFriday();

        return sprintf(
            'Only %s left until Rebeccaday, OMG!',
            dateinterval_to_string($timeLeft, 'i')
        );

    }

    private function getTimeUntilNextFriday(): \DateInterval
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $friday = new \DateTime('next friday', new \DateTimeZone('UTC'));

        return $now->diff($friday);
    }

    public function getDescription(): string
    {
        static $descriptions = [
            "(Yeah, Ah-Ah-Ah-Ah-Ah-Ark)",
            "Oo-ooh-ooh, hoo yeah, yeah",
            "Yeah, yeah",
            "Yeah-ah-ah",
            "Yeah-ah-ah",
            "Yeah-ah-ah",
            "Yeah-ah-ah",
            "Yeah, yeah, yeah",
            "Seven a.m., waking up in the morning",
            "Gotta be fresh, gotta go downstairs",
            "Gotta have my bowl, gotta have cereal",
            "Seein' everything, the time is goin'",
            "Tickin' on and on, everybody's rushin'",
            "Gotta get down to the bus stop",
            "Gotta catch my bus, I see my friends (My friends)",
            "Kickin' in the front seat",
            "Sittin' in the back seat",
            "Gotta make my mind up",
            "Which seat can I take?",
            "It's Friday, Friday",
            "Gotta get down on Friday",
            "Everybody's lookin' forward to the weekend, weekend",
            "Friday, Friday",
            "Gettin' down on Friday",
            "Everybody's lookin' forward to the weekend",
            "Partyin', partyin' (Yeah)",
            "Partyin', partyin' (Yeah)",
            "Fun, fun, fun, fun",
            "Lookin' forward to the weekend",
            "7:45, we're drivin' on the highway",
            "Cruisin' so fast, I want time to fly",
            "Fun, fun, think about fun",
            "You know what it is",
            "I got this, you got this",
            "My friend is by my right, ay",
            "I got this, you got this",
            "Now you know it",
            "Kickin' in the front seat",
            "Sittin' in the back seat",
            "Gotta make my mind up",
            "Which seat can I take?",
            "It's Friday, Friday",
            "Gotta get down on Friday",
            "Everybody's lookin' forward to the weekend, weekend",
            "Friday, Friday",
            "Gettin' down on Friday",
            "Everybody's lookin' forward to the weekend",
            "Partyin', partyin' (Yeah)",
            "Partyin', partyin' (Yeah)",
            "Fun, fun, fun, fun",
            "Lookin' forward to the weekend",
            "Yesterday was Thursday, Thursday",
            "Today i-is Friday, Friday (Partyin')",
            "We-we-we so excited",
            "We so excited",
            "We gonna have a ball today",
            "Tomorrow is Saturday",
            "And Sunday comes after ... wards",
            "I don't want this weekend to end",
            "R-B, Rebecca Black",
            "So chillin' in the front seat (In the front seat)",
            "In the back seat (In the back seat)",
            "I'm drivin', cruisin' (Yeah, yeah)",
            "Fast lanes, switchin' lanes",
            "Wit' a car up on my side (Woo!)",
            "(C'mon) Passin' by is a school bus in front of me",
            "Makes tick tock, tick tock, wanna scream",
            "Check my time, it's Friday, it's a weekend",
            "We gonna have fun, c'mon, c'mon, y'all",
            "It's Friday, Friday",
            "Gotta get down on Friday",
            "Everybody's lookin' forward to the weekend, weekend",
            "Friday, Friday",
            "Gettin' down on Friday",
            "Everybody's lookin' forward to the weekend",
            "Partyin', partyin' (Yeah)",
            "Partyin', partyin' (Yeah)",
            "Fun, fun, fun, fun",
            "Lookin' forward to the weekend",
            "It's Friday, Friday",
            "Gotta get down on Friday",
            "Everybody's lookin' forward to the weekend, weekend",
            "Friday, Friday",
            "Gettin' down on Friday",
            "Everybody's lookin' forward to the weekend",
            "Partyin', partyin' (Yeah)",
            "Partyin', partyin' (Yeah)",
            "Fun, fun, fun, fun",
            "Lookin' forward to the weekend",
        ];

        return $descriptions[array_rand($descriptions)];
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Friday', [$this, 'gottaGetDownOnFriday'], 'rebecca')];
    }
}
