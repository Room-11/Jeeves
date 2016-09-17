<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client\Actions;

use Amp\Artax\Request;
use Amp\Deferred;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

class ActionFactory
{
    public function createPostMessageAction(Request $request, ChatRoom $room): PostMessageAction
    {
        return new PostMessageAction($request, $room, new Deferred);
    }

    public function createEditMessageAction(Request $request): EditMessageAction
    {
        return new EditMessageAction($request, new Deferred);
    }

    public function createPinOrUnpinMessageAction(Request $request): PinOrUnpinMessageAction
    {
        return new PinOrUnpinMessageAction($request, new Deferred);
    }

    public function createUnstarMessageAction(Request $request): UnstarMessageAction
    {
        return new UnstarMessageAction($request, new Deferred);
    }
}
