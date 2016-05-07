<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient as ArtaxClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;

class HttpClient implements Plugin
{
    use CommandOnlyPlugin;

    const FLAGS = ['chrome', 'firefox', 'googlebot', 'nofollow'];

    const USER_AGENTS = [
        'chrome'    => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36',
        'firefox'   => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.1',
        'googlebot' => 'Googlebot/2.1 (+http://www.googlebot.com/bot.html)',
    ];

    private $chatClient;

    private $httpClient;

    public function __construct(ChatClient $chatClient, ArtaxClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getResult(Command $command): \Generator {
        $this->setClientOptions($command);

        try {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($this->getRequest($command));

            // there has to be a better way to revert to the current settings...
            $this->httpClient->setOption($this->httpClient::OP_FOLLOW_LOCATION, true);
            $this->httpClient->setOption($this->httpClient::OP_DEFAULT_USER_AGENT, $this->httpClient::USER_AGENT);

            yield from $this->chatClient->postMessage($this->formatResult($response));
        } catch (\RuntimeException $e) {
            yield from $this->chatClient->postMessage($e->getMessage());
        }
    }

    private function setClientOptions(Command $command)
    {
        foreach($command->getParameters() as $parameter) {
            if (!in_array($parameter, self::FLAGS, true)) {
                continue;
            }

            if ($parameter === 'nofollow') {
                $this->httpClient->setOption($this->httpClient::OP_FOLLOW_LOCATION, false);

                continue;
            }

            $this->setUserAgent($parameter);
        }
    }

    private function setUserAgent(string $userAgent)
    {
        $this->httpClient->setOption($this->httpClient::OP_DEFAULT_USER_AGENT, self::USER_AGENTS[$userAgent]);
    }

    private function getRequest(Command $command): HttpRequest
    {
        switch ($command->getCommandName()) {
            case 'get':
                return (new HttpRequest)->setUri($this->getRequestUrl($command));

            case 'head':
                return (new HttpRequest)
                    ->setMethod('HEAD')
                    ->setUri($this->getRequestUrl($command))
                ;

            case 'post':
                return (new HttpRequest())
                    ->setMethod('POST')
                    ->setUri($this->getRequestUrl($command))
                    ->setBody($this->getPostBody($command))
                ;
        }
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

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator
    {
        if (!$command->getParameters()) {
            return;
        }

        yield from $this->getResult($command);
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getHandledCommands(): array
    {
        return ['get', 'post', 'head'];
    }
}
