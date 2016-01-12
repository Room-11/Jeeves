<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Room;

use Room11\Jeeves\Fkey\Retriever as FkeyRetriever;
use Amp\Artax\Client as HttpClient;

class Collection
{
    private $fkeyRetriever;

    private $httpClient;

    private $rooms = [];

    public function __construct(FkeyRetriever $fkeyRetriever, HttpClient $httpClient)
    {
        $this->fkeyRetriever = $fkeyRetriever;
        $this->httpClient    = $httpClient;
    }

    public function join(int $roomId, string $chatKey): \Generator
    {
        if (array_key_exists($roomId, $this->rooms)) {
            return;
        }

        $this->rooms[$roomId] = (new Room($this->fkeyRetriever, $this->httpClient, $roomId));

        yield from $this->rooms[$roomId]->join($chatKey);
    }
}
