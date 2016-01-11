<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Room;

use Room11\Jeeves\Fkey\Retriever as FkeyRetriever;
use Amp\Artax\Client as HttpClient;
use Amp\Artax\Request;
use Amp\Artax\FormBody;

class Room
{
    private $fkeyRetriever;

    private $httpClient;

    private $roomId;

    public function __construct(FkeyRetriever $fkeyRetriever, HttpClient $httpClient, int $roomId)
    {
        $this->fkeyRetriever = $fkeyRetriever;
        $this->httpClient    = $httpClient;
        $this->roomId        = $roomId;
    }

    public function join(string $chatKey)
    {
        $body = (new FormBody)
            ->addField('text', 'testmessage' . time())
            ->addField('fkey', $chatKey)
        ;

        $request = (new Request)
            ->setUri('http://chat.stackoverflow.com/chats/' . $this->roomId . '/messages/new')
            ->setMethod('POST')
            ->setBody($body)
        ;

        $promise = $this->httpClient->request($request);

        yield $promise;
    }
}
