<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client\Actions;

use Amp\Deferred;
use Amp\Artax\Request as HttpRequest;
use Room11\Jeeves\Chat\Client\Entities\PostedMessage;
use Room11\Jeeves\Chat\Client\MessagePostFailureException;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;

class PostMessageAction extends Action
{
    private $room;

    public function __construct(HttpRequest $request, ChatRoom $room, Deferred $deferred)
    {
        parent::__construct($request, $deferred);

        $this->room = $room;
    }

    public function getMaxAttempts(): int
    {
        return 5;
    }

    public function processResponse($response, int $attempt, Logger $logger): int
    {
        if (isset($response["id"], $response["time"])) {
            $this->getPromisor()->succeed(new PostedMessage($this->room, $response["id"], $response["time"]));
            return self::SUCCESS;
        }

        if (!array_key_exists('id', $response)) {
            $logger->log(Level::ERROR, 'A JSON response that I don\'t understand was received', $response);
            $this->getPromisor()->fail(new MessagePostFailureException("Invalid response from server"));
            return self::FAILURE;
        }

        // sometimes we can get {"id":null,"time":null}
        // I think this happens when we repeat ourselves too quickly
        $delay = $attempt * 1000;
        $logger->log(Level::DEBUG, "Got a null message post response, waiting for {$delay}ms before trying again");

        return $delay;
    }
}
