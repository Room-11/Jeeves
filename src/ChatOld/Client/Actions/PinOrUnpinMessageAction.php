<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client\Actions;

use Room11\Jeeves\Chat\Client\MessageEditFailureException;
use Room11\Jeeves\Log\Level;

class PinOrUnpinMessageAction extends Action
{
    public function processResponse($response, int $attempt): int
    {
        if ($response === 'ok') {
            $this->succeed();
            return self::SUCCESS;
        }

        if($response === 'Only a room-owner can pin messages') {
            $errStr = 'Jeeves cannot pin this message because it is not an owner of this room.';
            $this->logger->log(Level::ERROR, $errStr, $response);
            $this->fail(new MessageEditFailureException($errStr));
        }

        $errStr = 'A JSON response that I don\'t understand was received';
        $this->logger->log(Level::ERROR, $errStr, $response);
        $this->fail(new MessageEditFailureException($errStr));

        return self::FAILURE;
    }
}
