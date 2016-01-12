<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

class Factory
{
    public function build(array $data): Message
    {
        $message = reset($data);

        if (isset($message['e'])) {
            return $this->buildEventMessage($message['e'][0]);
        }

        return new Heartbeat($message);
    }

    private function buildEventMessage(array $message): Message
    {
        switch ($message['event_type']) {
            case 1:
                return new NewMessage($message);

            case 2:
                return new EditMessage($message);

            case 6:
                return new StarMessage($message);

            case 10:
                return new DeleteMessage($message);

            default:
                return new Unknown($message);
        }
    }
}
