<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client\Actions;

use Amp\Artax\Request;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Log\Logger;

class ActionFactory
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function createPostMessageAction(Request $request, ChatRoom $room): PostMessageAction
    {
        return new PostMessageAction($this->logger, $request, $room);
    }

    public function createEditMessageAction(Request $request): EditMessageAction
    {
        return new EditMessageAction($this->logger, $request);
    }

    public function createPinOrUnpinMessageAction(Request $request): PinOrUnpinMessageAction
    {
        return new PinOrUnpinMessageAction($this->logger, $request);
    }

    public function createUnstarMessageAction(Request $request): UnstarMessageAction
    {
        return new UnstarMessageAction($this->logger, $request);
    }
}
