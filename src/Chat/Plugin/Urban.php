<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;

class Urban implements Plugin
{
    use CommandOnlyPlugin;

    private $chatClient;

    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getResult(Command $command): \Generator
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(
            'http://api.urbandictionary.com/v0/define?term=' . rawurlencode(implode(' ', $command->getParameters()))
        );

        $result = json_decode($response->getBody(), true);

        yield from $this->chatClient->postMessage($this->getMessage($result));
    }

    private function getMessage(array $result): string
    {
        if ($result['result_type'] === 'no_results')
        {
            return 'whatchoo talkin bout willis';
        }

        return sprintf(
            '[ [%s](%s) ] %s',
            $result['list'][0]['word'],
            $result['list'][0]['permalink'],
            str_replace("\r\n", ' ', $result['list'][0]['definition'])
        );
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
        return ['urban'];
    }
}
