<?php declare(strict_types=1);

namespace Room11\Jeeves\External;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use function Amp\all;

class BitlyClient
{
    const URL_BASE = 'https://api-ssl.bitly.com/v3/shorten';

    private $accessToken;

    private $httpClient;

    public function __construct(HttpClient $httpClient, string $accessToken)
    {
        $this->accessToken = $accessToken;
        $this->httpClient = $httpClient;
    }

    private function getAPIRequestURL(string $longURL): string
    {
        $result = self::URL_BASE . '?' . http_build_query([
            'access_token' => $this->accessToken,
            'longUrl' => $longURL,
        ]);

        return $result;
    }

    /**
     * @param string $longURL
     * @return \Generator|string
     */
    public function shorten(string $longURL): \Generator
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($this->getAPIRequestURL($longURL));

        $jsonBody = $response->getBody();
        $content  = json_try_decode($jsonBody, true);

        if($content["status_code"] === 200){
            return $content['data']['url'];
        }

        throw new \Exception('Error upon Bitly Response:' . $content["status_code"] . ' - ' . $content['status_txt']);
    }

    /**
     * @param string[] $longURLs
     * @return \Generator|string[]
     */
    public function shortenMulti(array $longURLs): \Generator
    {
        $requests = [];
        foreach ($longURLs as $longURL) {
            $requests[$longURL] = $this->httpClient->request($this->getAPIRequestURL($longURL));
        }

        /** @var HttpResponse[] $responses */
        $responses = yield all($requests);

        $result = [];
        foreach ($responses as $longURL => $response) {
            $result[$longURL] = json_try_decode($response->getBody(), true)["data"]["url"];
        }

        return $result;
    }
}
