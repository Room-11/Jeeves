<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client\Actions;

use Amp\Artax\Request as HttpRequest;
use Room11\Jeeves\Chat\Client\MessagePostFailureException;
use Room11\Jeeves\Chat\Client\PostedMessageTracker;
use Room11\Jeeves\Chat\Entities\PostedMessage;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;

class PostMessageAction extends Action
{
    private $tracker;
    private $text;

    public function __construct(
        Logger $logger,
        HttpRequest $request,
        ChatRoom $room,
        PostedMessageTracker $tracker,
        string $text
    ) {
        parent::__construct($logger, $request, $room);

        $this->tracker = $tracker;
        $this->text = $text;
    }

    public function getMaxAttempts(): int
    {
        return 5;
    }

    public function isValid(): bool
    {
        return $this->tracker->getLastPostedMessage($this->room) !== $this->text;
    }

    public function processResponse($response, int $attempt): int
    {
        if (isset($response["id"], $response["time"])) {
            $this->tracker->setLastPostedMessage($this->room, $this->text);
            $this->succeed(new PostedMessage($this->room, $response["id"], $response["time"]));
            return self::SUCCESS;
        }

        if (!array_key_exists('id', $response)) {
            $this->logger->log(Level::ERROR, 'A JSON response that I don\'t understand was received', $response);
            $this->fail(new MessagePostFailureException("Invalid response from server"));
            return self::FAILURE;
        }

        // sometimes we can get {"id":null,"time":null}
        // I think this happens when we repeat ourselves too quickly
        $delay = $attempt * 1000;
        $this->logger->log(Level::DEBUG, "Got a null message post response, waiting for {$delay}ms before trying again");

        return $delay;
    }
}
