<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

// interface does not have option constants :-(
use Amp\Artax\Client as HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client;
use function Amp\resolve;

class HttpRequester extends BasePlugin
{
    private const FLAGS = ['chrome', 'firefox', 'googlebot', 'nofollow'];

    private const USER_AGENTS = [
        'chrome'    => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36',
        'firefox'   => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.1',
        'googlebot' => 'Googlebot/2.1 (+http://www.googlebot.com/bot.html)',
    ];

    private $chatClient;

    private $httpClient;

    public function __construct(Client $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getResult(HttpRequest $request, Command $command)
    {
        try {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($request, $this->getClientOptions($command));

            return $this->chatClient->postMessage($command, $this->formatResult($response));
        } catch (\Throwable $e) {
            return $this->chatClient->postMessage($command, $e->getMessage());
        }
    }

    private function getClientOptions(Command $command)
    {
        $options = [];

        foreach($command->getParameters() as $parameter) {
            if (!in_array($parameter, self::FLAGS, true)) {
                continue;
            }

            if ($parameter === 'nofollow') {
                $options[HttpClient::OP_FOLLOW_LOCATION] = false;
                continue;
            }

            $options[HttpClient::OP_DEFAULT_USER_AGENT] = self::USER_AGENTS[$parameter];
        }

        return $options;
    }

    private function getRequestUrl(Command $command): string
    {
        $parameters = $command->getParameters();

        return end($parameters);
    }

    private function getPostBody(Command $command): string
    {
        $parameters = $command->getParameters();

        if (count($parameters) < 2) {
            throw new \RuntimeException('Usage: `!!post [options] postdata url`');
        }

        return $parameters[count($parameters) - 2];
    }

    private function formatResult(HttpResponse $response): string
    {
        $lines = [
            sprintf('%s %s', $this->getProtocol($response), $this->getStatus($response)),
        ];

        $lines = array_merge($lines, array_map(function ($key, $value) {
            return sprintf("%s: %s", $key, implode(';', $value));
        }, array_keys($response->getAllHeaders()), $response->getAllHeaders()));

        return sprintf('    %s', implode("\r\n    ", $lines));
    }

    private function getProtocol(HttpResponse $response): string
    {
        return 'HTTP/' . $response->getProtocol();
    }

    private function getStatus(HttpResponse $response): string
    {
        return sprintf('%s %s', $response->getStatus(), $response->getReason());
    }

    public function get(Command $command): Promise
    {
        $request = (new HttpRequest)
            ->setUri($this->getRequestUrl($command));

        return resolve($this->getResult($request, $command));
    }

    public function post(Command $command): Promise
    {
        $request = (new HttpRequest)
            ->setMethod('POST')
            ->setUri($this->getRequestUrl($command))
            ->setBody($this->getPostBody($command));

        return resolve($this->getResult($request, $command));
    }

    public function head(Command $command): Promise
    {
        $request = (new HttpRequest)
            ->setMethod('HEAD')
            ->setUri($this->getRequestUrl($command));

        return resolve($this->getResult($request, $command));
    }

    public function getDescription(): string
    {
        return 'Sends HTTP requests and displays the headers of the response';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint('GET', [$this, 'get'], 'get', 'Sends HTTP GET request and displays the headers of the response'),
            new PluginCommandEndpoint('POST', [$this, 'post'], 'post', 'Sends HTTP POST request and displays the headers of the response'),
            new PluginCommandEndpoint('HEAD', [$this, 'head'], 'head', 'Sends HTTP HEAD request and displays the headers of the response'),
        ];
    }
}
