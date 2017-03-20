<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client;
use function Room11\DOMUtils\domdocument_load_html;

class Horoscope extends BasePlugin
{
    private const HOROSCOPE_URL = "http://www.theonion.com/features/horoscope";
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

    public function __construct(Client $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    public function divine(Command $command)
    {
        $sign = strtolower((string)$command->getParameter(0));

        if ($command->hasParameters() && !array_key_exists($sign, self::SIGNS)) {
            return $this->chatClient->postReply(
                $command,
                "Now you're just making shit up."
            );
        }

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(self::HOROSCOPE_URL);

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postReply(
                $command,
                "The stars are dead. Why have you killed the stars?"
            );
        }

        $dom = domdocument_load_html($response->getBody());
        $xpath = new \DOMXPath($dom);

        if (!$command->hasParameters()) {
            $sign = $this->extractActiveSign($xpath);
        }

        return $this->chatClient->postMessage(
            $command,
            $this->formatResponse(
                $sign,
                $this->extractDate($sign, $xpath),
                $this->extractHoroscope($sign, $xpath)
            )
        );
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

    private function extractDate(string $sign, \DOMXPath $xpath): string
    {
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
        $horoscope = $xpath->evaluate("
            string(
                //li[contains(concat(' ', normalize-space(@class), ' '), ' astro-$sign ')][1]
                /div[2]
                /text()[last()]
            )
        ");

        if (!$horoscope) {
            throw new \RuntimeException("Could not extract horror show. I mean horoscope.");
        }

        return trim($horoscope);
    }

    private function formatResponse($sign, $date, $horoscope): string
    {
        return sprintf(
            "> %s %s | %s\n%s\n%s",
            self::SIGNS[$sign],
            ucfirst($sign),
            $date,
            $horoscope,
            self::HOROSCOPE_URL
        );
    }
}
