<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Room11\Jeeves\Chat\Client\Xhr as ChatClient;
use Room11\Jeeves\Chat\Command\Message;
use Amp\Artax\FormBody;
use Amp\Artax\Request;

class EvalCode implements Plugin
{
    const COMMAND = 'eval';

    private $chatClient;

    public function __construct(ChatClient $chatClient)
    {
        $this->chatClient = $chatClient;
    }

    public function handle(Message $message): \Generator
    {
        if (!$this->validMessage($message)) {
            return;
        }

        yield from $this->getResult($message);

        var_dump('HIIIIIIIIIIIIIIIIIIIIIT');
    }

    private function validMessage(Message $message): bool
    {
        return get_class($message) === 'Room11\Jeeves\Chat\Command\Command'
            && $message->getCommand() === self::COMMAND
            && $message->getParameters();
    }

    private function getResult(Message $message): \Generator
    {
        sleep(10);

        $body = (new FormBody)
            ->addField('title', '')
            ->addField('code', '<?php var_dump(\'foo\');')
        ;

        $request = (new Request)
            ->setUri('https://3v4l.org/new')
            ->setMethod('POST')
            ->setBody($body)
        ;

        $response = yield from $this->chatClient->request($request);

        var_dump($response);
    }

    private function getMessage(array $result): string
    {
        return sprintf(
            '[ [%s](%s) ] %s',
            $result['list'][0]['word'],
            $result['list'][0]['permalink'],
            str_replace("\r\n", ' ', $result['list'][0]['definition'])
        );
    }
}
