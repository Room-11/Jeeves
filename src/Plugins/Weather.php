<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\Jeeves\Utf8Chars;
use Room11\StackChat\Client\ChatClient;

class Weather extends BasePlugin {
    private const API_ENDPOINT = 'http://api.openweathermap.org/data/2.5/weather';

    private $chatClient;
    private $httpClient;
    private $apiKey;

    public function __construct($apiKey, ChatClient $chatClient, HttpClient $httpClient) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
    }

    public function search(Command $command) {
        if (!$command->hasParameters()) {
            return $this->chatClient->postReply(
                $command,
                /** @lang text */ "I'm not a psychic. HTH am I supposed to know you location?"
            );
        }

        $search = $command->getCommandText();

        $params = $this->getParams($search);

        $message = yield $this->chatClient->postMessage(
            $command,
            sprintf("_Getting weather for '%s'%s_", $search, Utf8Chars::ELLIPSIS)
        );

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            sprintf('%s?%s', self::API_ENDPOINT, http_build_query($params))
        );

        if ($response->getStatus() !== 200 && $response->getStatus() !== 404) {
            return $this->chatClient->editMessage(
                $message,
                sprintf(
                    "Sorry, the [OpenWeatherMap API](http://openweathermap.org) is currently unavailable. (%d)",
                    $response->getStatus()
                )
            );
        } else if ($response->getStatus() === 404) {
            return $this->chatClient->editMessage(
                $message,
                sprintf(
                    "Sorry, but I couldn't find a city named %s on their servers.",
                    $search
                )
            );
        }

        /** @var \stdClass $data */
        $data = @json_decode($response->getBody());

        return $this->chatClient->editMessage(
            $message,
            $this->getFormattedWeather($data)
        );
    }

    private function getFormattedWeather(\stdClass $data): string {
        return sprintf(
            "*%s*. **Maximum Temperature:** %d C, **Minimum Temperature:** %d C. **Humidity:** %d%%",
            ucwords($data->weather[0]->description),
            $data->main->temp_max - 273.15,
            $data->main->temp_min - 273.15,
            $data->main->humidity
        );
    }

    private function getParams(string $cityName): array {
        return [
            "q" => $cityName,
            "APPID" => $this->apiKey
        ];
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string {
        return "Returns the weather conditions for the specified city.";
    }

    public function getCommandEndpoints(): array {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'weather')];
    }
}