<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;
use function Amp\resolve;

class PHPComic extends BasePlugin
{
    private const MOODS = [
        'neutral',
        'angry',
        'sad',
        'grumpy',
        'hangme',
        'reply',
        'thelook',
        'wat',
    ];

    private $chatClient;

    private $httpClient;

    private $key;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, string $key)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->key        = $key;
    }

    public function comic(Command $command): Promise
    {
        return resolve(function() use ($command) {
            if (!$command->hasParameters(2)) {
                return $this->chatClient->postMessage(
                    $command,
                    sprintf('Usage: `!!comic <mood> <quote text>` Supported moods: %s', implode(', ', self::MOODS))
                );
            }

            if (!in_array($command->getParameter(0), self::MOODS, true)) {
                return $this->chatClient->postMessage(
                    $command,
                    sprintf('`%s` is not a valid mood. Supported moods: %s', $command->getParameter(0), implode(', ', self::MOODS))
                );
            }

            $url = yield $this->generateComic($command->getParameter(0), implode(' ', $command->getParameters(1)), $command);

            return $this->chatClient->postMessage($command, $url);
        });
    }

    private function generateComic(string $mood, string $text, Command $command): Promise
    {
        return resolve(function() use ($mood, $text, $command) {
            $body = new FormBody();
            $body->addField('api_key', $this->key);
            $body->addField('mood', $mood);
            $body->addField('text', $text);
            $request = (new HttpRequest)
                ->setMethod('POST')
                ->setUri('https://comic.pieterhordijk.com')
                ->setBody($body)
            ;

            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($request);

            return $response->getBody();
        });
    }

    public function getName(): string
    {
        return 'PHPComic';
    }

    public function getDescription(): string
    {
        return 'Converts text into an elephpant comic.';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('PHPComic', [$this, 'comic'], 'comic')];
    }
}
