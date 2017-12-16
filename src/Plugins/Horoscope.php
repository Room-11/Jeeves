<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;
use function Room11\DOMUtils\domdocument_load_html;

class Horoscope extends BasePlugin
{
    private const GLOBAL_HOROSCOPE_TAG_URL = "https://www.theonion.com/tag/horoscope";
    private const SIGNS = [
        "aries" => "♈",
        "taurus" => "♉",
        "gemini" => "♊",
        "cancer" => "♋",
        "leo" => "♌",
        "virgo" => "♍",
        "libra" => "♎",
        "scorpio" => "♏",
        "sagittarius" => "♐",
        "capricorn" => "♑",
        "aquarius" => "♒",
        "pisces" => "♓",
    ];

    private $chatClient;
    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    /*
     * we want to divine a specific sign so
     *
     * request the global horoscope page content
     * follow the first horoscope link there (should be the current one)
     * extract the specific sign
     */
    public function divine(Command $command)
    {

        if (!$command->hasParameters()) {
            return $this->chatClient->postReply(
                $command,
                "Nope, I don't support no parameter anymore."
            );
        }

        $sign = strtolower((string)$command->getParameter(0));

        if (!array_key_exists($sign, self::SIGNS)) {
            return $this->chatClient->postReply(
                $command,
                "Now you're just making shit up."
            );
        }

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(self::GLOBAL_HOROSCOPE_TAG_URL);

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postReply(
                $command,
                "The stars are dead. Why have you killed the stars?"
            );
        }

        $globalHoroscopePageDom = domdocument_load_html($response->getBody());
        $globalHoroscopeXPath = new \DOMXPath($globalHoroscopePageDom);

        $currentHoroscopeUrl = $this->extractCurrentHoroscopeUrl($globalHoroscopeXPath);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($currentHoroscopeUrl);

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postReply(
                $command,
                "The second stars are dead. It's better, but not quite there yet."
            );
        }

        $currentHoroscopeDom = domdocument_load_html($response->getBody());
        $currentHoroscopeXPath = new \DOMXPath($currentHoroscopeDom);

        if (!$command->hasParameters()) {
            $sign = $this->extractActiveSign($currentHoroscopeXPath);
        }

        return $this->chatClient->postMessage(
            $command,
            $this->formatResponse(
                $sign,
                $this->extractDate($sign, $currentHoroscopeXPath),
                $this->extractHoroscope($sign, $currentHoroscopeXPath),
                $currentHoroscopeUrl
            )
        );
    }

    private function extractCurrentHoroscopeUrl(\DOMXPath $xpath): string
    {
        $url = $xpath->evaluate("
            string(
                //a[contains(@class,'js_entry-link')][1]
                /@href
            )
        ");

        if (!$url) {
            throw new \RuntimeException("Could not extract current horoscope URL.");
        }

        return trim($url);
    }

    public function getDescription(): string
    {
        return "Divines your future. Your poor, poor future.";
    }

    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Horoscope', [$this, 'divine'], 'horoscope')];
    }

    private function extractActiveSign(\DOMXPath $xpath): string
    {
        $sign = $xpath->evaluate('
            string(
                //li[contains(concat(" ", normalize-space(@class), " "), " active ")][1]
                /div[1]
                /img[1]
                /@alt
            )
        ');

        if (!$sign) {
            throw new \RuntimeException("Could not extract current zodiac sign.");
        }

        return strtolower($sign);
    }

    /*
     * this routine, as the name expertly hints at, extracts the date from the horoscope page
     *
     * however, it's not there anymore, so let's just turn that off for now
     */
    private function extractDate(string $sign, \DOMXPath $xpath): string
    {
        // totally legit date
        return '';
        $date = $xpath->evaluate("
            string(
                //li[contains(concat(' ', normalize-space(@class), ' '), ' astro-$sign ')][1]
                /div[2]
                /h3[1]
                /span[contains(concat(' ', normalize-space(@class), ' '), ' date ')][1]
            )
        ");

        if (!$date) {
            throw new \RuntimeException("Could not extract date.");
        }

        return trim($date);
    }

    private function extractHoroscope(string $sign, \DOMXPath $xpath): string
    {
        $sign = ucfirst($sign);
        $horoscope = $xpath->evaluate("
            string(
                //h4[contains(text(), '$sign')]
                /following::p[1]
            )
        ");

        if (!$horoscope) {
            throw new \RuntimeException("Could not extract horror show. I mean horoscope.");
        }

        return trim($horoscope);
    }

    private function formatResponse($sign, $date, $horoscope, $url): string
    {
        return sprintf(
            "> %s %s | %s\n%s\n%s",
            self::SIGNS[$sign],
            ucfirst($sign),
            $date,
            $horoscope,
            $url
        );
    }
}
