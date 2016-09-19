<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client\Actions;

use Room11\Jeeves\Chat\Client\MessageEditFailureException;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;

class UnstarMessageAction extends Action
{
    public function getMaxAttempts(): int
    {
        return 5;
    }

    public function processResponse($response, int $attempt, Logger $logger): int
    {
        if ($response === 'ok') {
            $this->getPromisor()->succeed();
            return self::SUCCESS;
        }

        $errStr = 'A JSON response that I don\'t understand was received';
        $logger->log(Level::ERROR, $errStr, $response);
        $this->getPromisor()->fail(new MessageEditFailureException($errStr));

        return self::FAILURE;
    }
}
