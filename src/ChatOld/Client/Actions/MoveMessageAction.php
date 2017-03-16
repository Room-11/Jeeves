<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client\Actions;

use Room11\Jeeves\Chat\Client\ActionExecutionFailureException;
use Room11\Jeeves\Log\Level;

class MessageMoveFailureException extends ActionExecutionFailureException {}

class MoveMessageAction extends Action
{
    public function processResponse($response, int $attempt): int
    {
        if (is_int($response)) {
            $this->succeed();
            return self::SUCCESS;
        }

        $errStr = 'A JSON response that I don\'t understand was received';
        $this->logger->log(Level::ERROR, $errStr, $response);
        $this->fail(new MessageMoveFailureException($errStr));

        return self::FAILURE;
    }
}
