<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Message;

class Factory
{
    public function build(array $data): Message
    {
        $message = reset($data);

        if (isset($message['e']) && $message['e'][0]['event_type'] === 1) {
            return new NewMessage($message['e'][0]);
        }

        if (isset($message['e']) && $message['e'][0]['event_type'] === 2) {
            return new EditMessage($message['e'][0]);
        }

        if (isset($message['e']) && $message['e'][0]['event_type'] === 6) {
            return new StarMessage($message['e'][0]);
        }

        if (!isset($message['e'])) {
            return new heartbeat($message);
        }

        return new Unknown($message);
    }
}
