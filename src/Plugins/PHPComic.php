<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use function Amp\resolve;
use PeeHaa\PHPComicGenerator\Generator;
use PeeHaa\PHPComicGenerator\Image;
use PeeHaa\PHPComicGenerator\Type;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;

class PHPComic extends BasePlugin
{
    private const MOODS = [
        'neutral',
        'angry',
    ];

    private $chatClient;

    private $httpClient;

    private $comicGenerator;

    private $key;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, Generator $comicGenerator, string $key)
    {
        $this->chatClient     = $chatClient;
        $this->httpClient     = $httpClient;
        $this->comicGenerator = $comicGenerator;
        $this->key            = $key;
    }

    public function quote(Command $command): Promise
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

            // @todo: make this call async
            yield $this->generateComic('neutral.png', implode(' ', $command->getParameters(1)), $command);

            return $this->chatClient->postMessage($command, 'Generating quote...');
        });
    }

    private function generateComic(string $mood, string $text, Command $command): Promise
    {
        return resolve(function() use ($mood, $text, $command) {
            $image = new Image(\Room11\Jeeves\PHP_COMIC_SOURCE_DIRECTORY , new Type(Type::NEUTRAL));
var_dump('generating comic');
            $comic = $this->comicGenerator->generate($image, $text);
var_dump('creating post body');
            $body = new FormBody();
            $body->addField('api_key', $this->key);
            $body->addFile('userfile', $comic->getPath());
            $request = (new HttpRequest)
                ->setMethod('POST')
                ->setUri('https://comic.pieterhordijk.com')
                ->setBody($body)
            ;
var_dump('sending post body');
            /** @var HttpResponse $response */
            $response = $this->httpClient->request($request);

            yield $response;
var_dump('post sent');
            $this->chatClient->postMessage($command, 'Comic posted to webservice');

            yield \Amp\File\unlink($comic->getPath());

            var_dump($response->getBody());
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
        return [new PluginCommandEndpoint('PHPComic', [$this, 'quote'], 'quote')];
    }
}
